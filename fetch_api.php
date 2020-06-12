#!/usr/bin/env php

<?php

require_once('vendor/autoload.php');

$issue = isset($argv[1]) ? $argv[1] : '2020_01';
$src  = realpath('issues/' . $issue . '/src');
$dist = realpath('issues/' . $issue . '/dist');

$object = new PCBIS2PDF\PCBIS2PDF;
    $object->setImagePath($dist . '/images');
    $object->setCachePath($dist . '/.cache');

    foreach (glob($src . '/csv/*.csv') as $dataFile) {
        $fromCSV = $object->CSV2PHP($dataFile, ';');
        $data = $object->processData($fromCSV);
        $object->PHP2CSV($data, $dist . '/csv/' . basename($dataFile));
    }
