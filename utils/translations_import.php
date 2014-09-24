<?php

/*
 * Update all translations on project and build file with unique strings
 * 
 * return file: translations_result.php
 */

$fp = @fopen('result/translations_import.csv', 'r');

if (!is_resource($fp)) {
    die('Can`t open file');
}

$array = array();
$keys = array();

$first = true;

while (($data = fgetcsv($fp)) !== FALSE) {
    if ($first) {
        $keys = $data;
        $first = false;

        foreach ($keys as $value) {
            $array[$value] = array();
        }
    } else {
        foreach ($keys as $key => $value) {
            array_push($array[$value], $data[$key]);
        }
    }
}

foreach ($array['en_new'] as $key => $value) {
    if (strlen($value)) {
        $array['en'][$key] = $array['en_new'][$key];
    }
}

for ($i = 2; $i < count($keys); $i++) {
    $result = "<?php\nreturn " . var_export(array_combine($array['en'], $array[$keys[$i]]), true) . ";";
    $result = str_replace('\\\n', '\n', $result);
    file_put_contents('result/' . $keys[$i] . '.php', $result);
}
