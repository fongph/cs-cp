<?php

require __DIR__ . '/../vendor/autoload.php';

$config = include __DIR__ . '/../build.php';
$di = new System\DI();
$di->set('config', $config);

require __DIR__ . '/../bootstrap.php';

$mainDb = $di->get('db');

$dataDb = $di->get('dataDb');

$deivces = $mainDb->query("SELECT `id`, `os` FROM `devices`")->fetchAll(PDO::FETCH_ASSOC);

$devicesCount = count($deivces);

$i = 0;

foreach ($deivces as $device) {
    if ($device['os'] == 'android') {
        $dataDb->exec("UPDATE `browser_history` SET `browser` = 'Chrome' WHERE `dev_id` = {$device['id']}");
    } else {
        $dataDb->exec("UPDATE `browser_history` SET `browser` = 'Safari' WHERE `dev_id` = {$device['id']}");
    }
    
    echo 'Devices processed: ' . ++$i . '/' . $devicesCount . "\n";
}