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

$result = "<?php\nreturn " . var_export(array_combine($data, $data), true) . ";";
$result = str_replace('\\\n', '\n', $result);
file_put_contents('translations_result.php', $result);