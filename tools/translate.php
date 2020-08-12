<?php

/*
 * New Translation system base on YAML files
 * We need to edit yml file for each languages
 * Use administration json node for Vue.js interface and backend for the messages to translate
 * Use the log file for messages not to be translated
 * /src/Translations/yml/en-GB.yml
 * /src/Translations/yml/de-DE.yml
 * /src/Translations/yml/fr-FR.yml
 * /src/Translations/yml/log.yml
 *
 * Execute this script to generate files
 *
 * Installation de YAML PARSER
 *
 * sudo apt-get install php5-dev libyaml-dev
 * sudo pecl install yaml
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$csvFolder = '/src/Translations/';
$jsonFolder = '/src/app/administration/src/module/lengow-connector/snippet/';

$directory = dirname(dirname(__FILE__)) . $csvFolder . 'yml/';
$listFiles = array_diff(scandir($directory), ['..', '.', 'index.php']);
$listFiles = array_diff($listFiles, ['en-GB.yml']);
array_unshift($listFiles, 'en-GB.yml');

foreach ($listFiles as $list) {
    $ymlFile = yaml_parse_file($directory . $list);
    $locale = basename($directory . $list, '.yml');
    if ($list === 'log.yml') {
        $ymlContent = $ymlFile['en'];
        $fp = fopen(dirname(dirname(__FILE__)) . $csvFolder . 'en-GB.csv', 'a+');
    } else {
        $ymlContent = $ymlFile[substr($locale, 0, 2)];
        if (isset($ymlContent['administration'])) {
            $jsonContent = json_encode($ymlContent['administration'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $fp = fopen(dirname(dirname(__FILE__)) . $jsonFolder . $locale . '.json', 'w+');
            fwrite($fp, $jsonContent);
            fclose($fp);
        }
        $fp = fopen(dirname(dirname(__FILE__)) . $csvFolder . $locale . '.csv', 'w+');
    }
    if ($ymlContent !== null) {
        foreach ($ymlContent as $key => $categories) {
            if ($key === 'backend') {
                writeCsv($fp, $categories);
            }
        }
    }
    fclose($fp);
}

function writeCsv($fp, $text, &$frontKey = array())
{
    if (is_array($text)) {
        foreach ($text as $k => $v) {
            $frontKey[] = $k;
            writeCsv($fp, $v, $frontKey);
            array_pop($frontKey);
        }
    } else {
        $line = join('.', $frontKey) . '|' . str_replace("\n", '<br />', $text) . PHP_EOL;
        fwrite($fp, $line);
    }
}
