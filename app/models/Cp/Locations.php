<?php

namespace Models\Cp;

class Locations extends BaseModel
{

    public function getPoints($devId, $date)
    {
        if (!$this->_validateDate($date)) {
            return false;
        }

        $devId = $this->getDb()->quote($devId);
        $timeFrom = $this->_getDateTimestamp($date);
        $timeTo = $timeFrom + 24 * 3600;
        return $this->getDb()->query("SELECT `address`, TRIM(TRAILING '0' FROM `latitude`) as latitude, TRIM(TRAILING '0' FROM `longitude`) as longitude, `accuracy`, `timestamp` FROM `gps_log` WHERE `dev_id` = {$devId} AND `timestamp` BETWEEN {$timeFrom} AND {$timeTo} ORDER BY `timestamp`")->fetchAll(\PDO::FETCH_NUM);
    }

    public function getLastPointTime($devId)
    {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT MAX(`timestamp`) FROM `gps_log` WHERE `dev_id` = {$devId}")->fetchColumn();
    }

    protected function _validateDate($date)
    {
        $parts = explode('-', $date);
        return (count($parts) === 3) && checkdate($parts[0], $parts[1], $parts[2]);
    }

    protected function _getDateTimestamp($dateString)
    {
        $parts = explode('-', $dateString);
        return mktime(0, 0, 0, $parts[0], $parts[1], $parts[2]);
    }

    public function getDayPoints($devId, $dayStart)
    {
        $devId = $this->getDb()->quote($devId);
        $timeFrom = $this->getDb()->quote($dayStart);
        $timeTo = $this->getDb()->quote($dayStart + 24 * 3600);

        return $this->getDb()->query("SELECT
                ge.`timestamp`,
                ge.`type`,
                ge.`address`,
                gz.`id` as zone_id,
                gz.`name` as zone,
                TRIM(TRAILING '0' FROM ge.`latitude`) as latitude,
                TRIM(TRAILING '0' FROM ge.`longitude`) as longitude,
                ge.`accuracy`,
                ge.`email_notified`
            FROM `geo_events` ge
            LEFT JOIN `geo_zones` gz ON gz.`id` = ge.`zone_id`
            WHERE 
                ge.`dev_id` = {$devId} AND
                ge.`timestamp` BETWEEN {$timeFrom} AND {$timeTo}
            ORDER BY `timestamp`")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getLastPointTimestamp($devId)
    {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT MAX(`timestamp`) FROM `geo_events` WHERE `dev_id` = {$devId}")->fetchColumn();
    }

    public function hasZones($devId)
    {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT `id` FROM `geo_zones` WHERE `dev_id` = {$devId} AND `deleted` = 0 AND `enable` = 1 LIMIT 1")->fetchColumn() !== false;
    }

    public function getZones($devId)
    {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT `latitude`, `longitude`, `radius`, `name` FROM `geo_zones` WHERE `dev_id` = {$devId} AND `deleted` = 0 AND `enable` = 1")->fetchAll();
    }

    public function getiCloudPoint($devId)
    {
        $pdo = $this->di->get('db');

        $devId = $pdo->quote($devId);

        $data = array(
            'apple_id' => 'willy.dixie007@icloud.com',
            'apple_password' => 'WillyDixie0075',
            'device_hash' => 'd9b3c75a0af8dc2a13f2c9dc843e94ecf758fc10'
            //'s7uavowVGVNltPTgLUTX5MTxrHXDVB6l3FNa4Hm5xMBLIySXf2HXl+HYVNSUzmWV'
        );

        $iCloud = new \CS\ICloud\Backup($data['apple_id'], $data['apple_password']);
        $devices = $iCloud->getDevices();
        
        $icloudDevice = null;
        foreach ($devices as $device) {
            if ($device['backupUDID'] == $data['device_hash']) {
                $icloudDevice = $device;
                break;
            }
        }
        
        if ($icloudDevice === null) {
            die('Sosi pidar!');
        }
        
        $sosumi = new Sosumi($data['apple_id'], $data['apple_password']);
        
        p($icloudDevice);
        d($sosumi->devices);
        
        $found = array();
        
        foreach ($sosumi->devices as $locationData) {
            if ($icloudDevice['DeviceName'] == $locationData->name && $icloudDevice['ProductType'] == $locationData->rawDeviceModel) {
                $found[] = $locationData->id;
            }
        }
        
        p(count($found));
        
        //p($devices);
        
        die;
        
//        $data = $pdo->query("SELECT `apple_id`, `apple_password`, `device_hash` FROM `devices_icloud` WHERE `dev_id` = {$devId} LIMIT 1")->fetch();
//        
//        if ($data === false) {
//            throw new Exception("Device not found");
//        }

        $sosumi = new Sosumi($data['apple_id'], $data['apple_password']);
        return $sosumi->locate($data['device_hash'], 20);
    }

}
