<?php

/*
 * Check MD5
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$base = dirname(__FILE__, 2);
$fp = fopen($base . '/src/Config/checkmd5.csv', 'w+');

$listFolders = [
    '/src/Components',
    '/src/Controller',
    '/src/DependencyInjection',
    '/src/Entity',
    '/src/EntityExtension',
    '/src/Exception',
    '/src/Factory',
    '/src/Migration',
    '/src/Resources/app',
    '/src/Resources/config',
    '/src/Resources/public/flag',
    '/src/Service',
    '/src/Storefront',
    '/src/Subscriber',
    '/src/Util',
];

$filePaths = [
    $base . '/composer.json',
    $base . '/src/LengowConnector.php',
    $base . '/src/Resources/public/bag.png',
    $base . '/src/Resources/public/clock.png',
    $base . '/src/Resources/public/home-orders.png',
    $base . '/src/Resources/public/home-products.png',
    $base . '/src/Resources/public/home-settings.png',
    $base . '/src/Resources/public/lengow-blue.png',
    $base . '/src/Resources/public/lengow-white-big.png',
    $base . '/src/Resources/public/plane.png',
    $base . '/src/Translations/de-DE.csv',
    $base . '/src/Translations/en-GB.csv',
    $base . '/src/Translations/fr-FR.csv',
];

foreach ($listFolders as $folder) {
    if (file_exists($base . $folder)) {
        $result = explorer($base . $folder);
        $filePaths = array_merge($filePaths, $result);
    }
}
foreach ($filePaths as $filePath) {
    if (file_exists($filePath)) {
        $checksum = [str_replace($base, '', $filePath) => md5_file($filePath)];
        writeCsv($fp, $checksum);
    }
}
fclose($fp);

function explorer($path)
{
    $paths = [];
    if (is_dir($path)) {
        $me = opendir($path);
        while ($child = readdir($me)) {
            if ($child !== '.' && $child !== '..' && $child !== 'checkmd5.csv') {
                $result = explorer($path . DIRECTORY_SEPARATOR.$child);
                $paths = array_merge($paths, $result);
            }
        }
    } else {
        $paths[] = $path;
    }
    return $paths;
}

function writeCsv($fp, $text, &$frontKey = [])
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
