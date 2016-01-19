<?php

set_time_limit(0);

require __DIR__ . '/../vendor/autoload.php';

$config = include __DIR__ . '/../build.php';
$di = new System\DI();
$di->set('config', $config);

require __DIR__ . '/../bootstrap.php';

$data = array(
    'apple_id' => 'willy.dixie007@icloud.com',
    'apple_password' => 'WillyDixie17'
);

$db = $di['db'];

$result = $db->query("SELECT * FROM `devices_icloud` WHERE `serial_number` = ''");

while ($data = $result->fetch(\PDO::FETCH_ASSOC)) {
    try {
        $iCloud = new \CS\ICloud\Backup($data['apple_id'], $data['apple_password']);
        $foundDevices = $iCloud->getDevices();

        foreach ($foundDevices as $device) {
            if ($device['backupUDID'] == $data['device_hash']) {
                $id = $db->quote($data['id']);
                $serial = $db->quote($device['SerialNumber']);
                $db->exec("UPDATE `devices_icloud` SET `serial_number` = {$serial} WHERE `id` = {$id}");
                echo $device['SerialNumber'] . PHP_EOL;
            }
        }
    } catch (Exception $e) {
        // ignore
    }

    echo $data['id'] . PHP_EOL;
}