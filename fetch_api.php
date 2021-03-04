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
    $csvArray = Pcbis\Spreadsheets::csv2array($csvFile, ';');

    # Load list of ISBNs to be blocked per category, useful if they exist twice
    $blocklist = [];

    if (file_exists($blockFile = realpath('issues/' . $issue . '/config/block-list.json'))) {
        $blockList = file_get_contents($blockFile);
        $blockList = json_decode($blockList, true);
    }

    # Load list of age recommendations, replacing improper ones
    $properAges = [];

    if (file_exists($ageFile = realpath('issues/' . $issue . '/config/proper-ages.json'))) {
        $properAges = file_get_contents($ageFile);
        $properAges = json_decode($properAges, true);
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
        } catch (\Exception $e) {
            # TODO: Add data from $csvData as backup
            continue;
        }

        # Detect empty age recommendation
        if ($node['Altersempfehlung'] === '') {
            $node['Altersempfehlung'] = 'Keine Altersangabe';
        }

        # Replace improper age recommendation
        if (isset($properAges[$isbn])) {
            $node['Altersempfehlung'] = $properAges[$isbn];
        }

        $isbns[] = $isbn;
        $data[]  = $node;
    }

    $sorted = Butler::sort($data, 'AutorIn', 'asc');

    # Create updated CSV file
    Pcbis\Spreadsheets::array2csv($sorted, $dist . '/csv/' . basename($csvFile));
}
