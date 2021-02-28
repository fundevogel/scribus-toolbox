#!/usr/bin/env php

<?php

require_once('vendor/autoload.php');

use Pcbis\Helpers\Butler;

if (!isset($argv[1])) {
    throw new Exception('No issue identifier provided!');
}

$issue = $argv[1];

$src   = realpath('issues/' . $issue . '/src');
$dist  = realpath('issues/' . $issue . '/dist');

# Authenticate with KNV's API
$credentials = file_get_contents('knv.login.json');
$credentials = json_decode($credentials, true);

# Initialize & set data cache path
$object = new Pcbis\Webservice($credentials, $dist . '/.cache');

foreach (glob($src . '/csv/*.csv') as $csvFile) {
    # Load raw CSV file from 'Titelexport'
    $csvArray = Butler::csv2array($csvFile, ';');

    # Load list of ISBNs to be blocked per category,
    # for example because they exist twice
    $blocklist = [];

    if (file_exists($blockFile = realpath('issues/' . $issue . '/meta/blockList.json'))) {
        $blockList = file_get_contents($blockFile);
        $blockList = json_decode($blockList, true);
    }

    $isbns = [];
    $data  = [];

    # Get category
    $category = basename(explode('.', $csvFile)[0]);

    # Retrieve data for every book
    foreach ($csvArray as $csvData) {
        $isbn = $csvData['ISBN'];

        # Skip duplicate ISBNs
        if (in_array($isbn, $isbns)) {
            continue;
        }

        # Skip blocked ISBNs
        if (isset($blockList[$category]) && in_array($isbn, $blockList[$category])) {
            continue;
        }

        # Provide base information first
        # This is necessary when detecting improper age rating,
        # since books & audiobooks have different columns
        $node = [
            'ISBN'                => $isbn,
            'Titel'               => '',
            'Untertitel'          => '',
            'Preis'               => '',
            'Erscheinungsjahr'    => '',
            'Altersempfehlung'    => '',
            'Inhaltsbeschreibung' => '',
            'AutorIn'             => $csvData['AutorIn'],
        ];

        try {
            # Fetch bibliographic data from API
            $book = $object->load($isbn);
            $bookData = $book->export();

            # Prevent comma-separated `author` being overridden
            unset($bookData['AutorIn']);

            $node = array_merge($node, $bookData);

            # Set image path
            $imagePath = $dist . '/images';
            $book->setImagePath($imagePath);

            # Download book cover
            $imageName = Butler::slug($book->title());
            $cover = $book->downloadCover($imageName);

            $node['@Cover'] = '';

            if ($cover && file_exists($imagePath . '/' . $imageName . '.jpg')) {
                $node['@Cover'] = $imageName . '.jpg';
            }

            # Detect empty age recommendation
            if ($node['Altersempfehlung'] === '') {
                $node['Altersempfehlung'] = 'Keine Altersangabe';
            }
        } catch (\Exception $e) {
            # TODO: Add data from $csvData as backup
            continue;
        }

        $isbns[] = $isbn;
        $data[]  = $node;
    }

    $sorted = Butler::sort($data, 'AutorIn', 'asc');

    # Create updated CSV file
    Butler::array2csv($sorted, $dist . '/csv/' . basename($csvFile));
}
