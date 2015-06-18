<?php

set_time_limit(0);

require __DIR__ . '/../vendor/autoload.php';

$config = include __DIR__ . '/../build.php';
$di = new System\DI();
$di->set('config', $config);

require __DIR__ . '/../bootstrap.php';

$data = array(
    'apple_id' => 'willy.dixie007@icloud.com',
    'apple_password' => 'WillyDixie0075'
);

$sosumi = new \Models\Cp\Locations\Sosumi($data['apple_id'], $data['apple_password']);
$iCloud = new \CS\ICloud\Backup($data['apple_id'], $data['apple_password']);

$foundDevices = $iCloud->getDevices();

echo 'Backup devices: ' . PHP_EOL;
foreach ($foundDevices as $device) {
    echo $device['backupUDID'] . ' - ' . $device['DeviceName'] . PHP_EOL;
}

echo 'Location devices: ' . PHP_EOL;
foreach ($sosumi->devices as $key => $locationData) {
    echo $key . ' - ' . $locationData['name'] . PHP_EOL;
}