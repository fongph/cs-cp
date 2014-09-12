<?php

/*
 * Update all translations on project and build file with unique strings
 * 
 * return file: translations_result.php
 */

$result = "<?php\nreturn " . var_export(array_combine($data, $data), true) . ";";
$result = str_replace('\\\n', '\n', $result);
file_put_contents('translations_result.php', $result);