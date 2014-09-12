<?php

/*
 * Get all translations on project and build file with unique strings
 * 
 * return file: translations_result.php
 */

function getFiles($dir = ".")
{
    $files = array();
    if ($handle = opendir($dir)) {
        while (false !== ($item = readdir($handle))) {
            if (is_file("$dir/$item")) {
                $files[] = "$dir/$item";
            } elseif (is_dir("$dir/$item") && ($item != ".") && ($item != "..")) {
                $files = array_merge($files, getFiles("$dir/$item"));
            }
        }
        closedir($handle);
    }
    return $files;
}

function getStrings($filename)
{
    $subject = file_get_contents($filename);
    preg_match_all("#_\('(.*)(?:'\)|'\,)#U", $subject, $matches);
    if (isset($matches[1])) {
        return $matches[1];
    }

    return false;
}

function processDirectory($dir, &$data)
{
    $files = getFiles($dir);
    foreach ($files as $file) {
        $list = getStrings($file);
        if ($list) {
            foreach ($list as $listItem) {
                $listItem = stripslashes(str_replace('\n', '\\\n', $listItem));
                if (!in_array($listItem, $data)) {
                    array_push($data, $listItem);
                }
            }
        }
    }
    
}

$data = array();

processDirectory('../controllers', $data);
processDirectory('../models', $data);
processDirectory('../templates', $data);

$locales = array('ru-RU');
$translations = array();

foreach ($locales as $value) {
    $translations[$value] = require '../locales/' . $value . '.php';
}

$fp = @fopen('result/translations_export.csv', 'w');

if (!is_resource($fp)) {
    die('Can`t open file');
}

fputcsv($fp, array_merge(array('en', 'en_new'), $locales));

foreach ($data as $value) {
    $array = array($value, '');
    foreach ($locales as $locale) {
        if (isset($translations[$locale][$value])) {
            array_push($array, $translations[$locale][$value]);
        } else {
            array_push($array, '');
        }
    }
    fputcsv($fp, $array);
}
fclose($fp);

echo count($data) . ' records exported';