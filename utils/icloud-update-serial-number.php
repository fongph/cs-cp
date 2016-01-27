<?php

var_dump('9e3156b537de1cea17a389fd1c9faaa8cb870701');
var_dump(sha1('CameraRollDomain-Media/DCIM/100APPLE/IMG_0004.JPG'));
var_dump(sha1('HomeDomain-Library/SMS/sms.db'));


die; 

set_time_limit(0);

require __DIR__ . '/../vendor/autoload.php';

$config = include __DIR__ . '/../build.php';
$di = new System\DI();
$di->set('config', $config);

require __DIR__ . '/../bootstrap.php';

$data = array(
    'apple_id' => 'Michaeltcaraballo@icloud.com',
    'apple_password' => 'Il1kepie1'
);

$cloudClient = new \CS\ICloud\CloudClient($data['apple_id'], $data['apple_password']);

$account = new \CS\ICloud\CloudKit\CkAccount($cloudClient);

foreach ($account->getDevices() as $value) {
    var_dump($value->getDeviceName());
    var_dump($value->getMarketingName());
    var_dump($value->getSerialNumber());
    var_dump($value->getProductType());
    var_dump($value->getProductVersion());
    //var_dump($value->getLastSnapshot());
    var_dump($value->getLastCommitted());
    echo '---------------------------------' . PHP_EOL;
}

die;

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