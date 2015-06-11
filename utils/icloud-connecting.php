<?php

set_time_limit(0);

require __DIR__ . '/../vendor/autoload.php';

$config = include __DIR__ . '/../build.php';
$di = new System\DI();
$di->set('config', $config);

require __DIR__ . '/../bootstrap.php';

$config = array(
    'db' => array('host' => '188.40.64.2', 'username' => 'ci_user', 'password' => 'qmsgrSR8qhxeNSC44533hVBqwNajd62z2QtXwN6E', 'dbname' => 'main', 'options' => array(1002 => 'set names utf8;', 3 => 2, 19 => 2))
);

$pdo = new \PDO("mysql:host={$config['db']['host']};dbname={$config['db']['dbname']}", $config['db']['username'], $config['db']['password'], $config['db']['options']);

//$devices = $pdo->query("SELECT * FROM `devices_icloud` GROUP BY `apple_id`")->fetchAll(PDO::FETCH_ASSOC);
$devices = $pdo->query("SELECT * FROM `devices_icloud` WHERE `apple_id` = 'zoienagy@icloud.com'")->fetchAll(PDO::FETCH_ASSOC);

$result = array(
    'success' => 0,
    'fail' => 0,
    'noDevices' => 0,
    'notFound' => 0,
    'moreThanOne' => 0,
    'authError' => 0
);

foreach ($devices as $data) {
    echo "Device {$data['dev_id']} - {$data['apple_id']}\n";

    try {
        $iCloud = new \CS\ICloud\Backup($data['apple_id'], $data['apple_password']);
        $foundDevices = $iCloud->getDevices();
    } catch (Exception $e) {
        echo "    Auth error\n";
        $result['authError'] ++;
        $result['fail'] ++;
        continue;
    }

    $icloudDevice = null;
    foreach ($foundDevices as $device) {
        if ($device['backupUDID'] == $data['device_hash']) {
            $icloudDevice = $device;
            break;
        }
    }

    if ($icloudDevice === null) {
        echo "    no device on icloud account\n";
        $result['fail'] ++;
        $result['noDevices'] ++;
        // no device on icloud account
    } else {
        $sosumi = new \Models\Cp\Sosumi($data['apple_id'], $data['apple_password']);

        $totalDevices = count($sosumi->devices);
        echo "    total devices: {$totalDevices}\n";
        
        $found = array();

        foreach ($sosumi->devices as $locationData) {
            if ($icloudDevice['DeviceName'] == $locationData->name && $icloudDevice['ProductType'] == $locationData->rawDeviceModel) {
                $found[] = $locationData->id;
            }
        }
        
        $count = count($found);
        if ($count > 1) {
//            echo var_dump($icloudDevice['DeviceName']);
//            echo var_dump($icloudDevice['ProductType']);
//            echo var_dump($sosumi->devices);
            echo "    more than one entry find ({$count})\n";
            $result['moreThanOne'] ++;
            $result['fail'] ++;
        } elseif ($count == 0) {
            echo "    entry not found\n";
            $result['notFound'] ++;
            $result['fail'] ++;
        } else {
            echo "    success\n";
            $result['success'] ++;
        }
    }
    
    if (count($devices)) {
        echo var_dump($sosumi->devices) . PHP_EOL;
    }
}

echo "\n\nTotal stat:\n";

$total = $result['success'] + $result['fail'];

echo "    Total: {$total}\n";
echo "    Success: {$result['success']}\n";
echo "    Fail: {$result['fail']}\n";
echo "    No devices on account: {$result['noDevices']}\n";
echo "    Entry not found: {$result['notFound']}\n";
echo "    More than one entry find: {$result['moreThanOne']}\n";
echo "    Auth error: {$result['authError']}\n";
