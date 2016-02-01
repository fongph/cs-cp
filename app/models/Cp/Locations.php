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

    public function getLastPoint($devId)
    {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT * FROM `geo_events` WHERE `dev_id` = {$devId} AND `timestamp` = (SELECT MAX(`timestamp`) FROM `geo_events` WHERE `dev_id` = {$devId})")->fetch();
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

    public function getiCloudDeviceCredentials($devId)
    {
        $pdo = $this->di->get('db');
        $devId = $pdo->quote($devId);

        return $pdo->query("SELECT 
                                di.`apple_id`,
                                di.`apple_password`,
                                d.`unique_id` serial_number
                            FROM `devices_icloud` di
                            INNER JOIN `devices` d ON d.`id` = di.`dev_id`
                            WHERE
                                di.`dev_id` = {$devId} LIMIT 1")->fetch();
    }

    public function getDeviceLocationServiceCredentials($devId)
    {
        $pdo = $this->di->get('db');

        $devId = $pdo->quote($devId);

        return $pdo->query("SELECT `apple_id`, `apple_password`, `location_device_hash` FROM `devices_icloud` WHERE `dev_id` = {$devId} AND `location_device_hash` != '' LIMIT 1")->fetch();
    }

    /* getDeviceColor */
    private function getDeviceColor($model, $color)
    {
        $colors = array(
            'iPhone3,1' => array(
                null => 'Black',
                'white' => 'White'
            ),
            'iPhone3,2' => 'iPhone3,1',
            'iPhone3,3' => 'iPhone3,1',
            'iPhone4,1' => 'iPhone3,1',
            'iPhone5,1' => array(
                '3b3b3c-99989b' => 'Black',
                'e1e4e3-d7d9d8' => 'White'
            ),
            'iPhone5,2' => 'iPhone5,1',
            'iPhone5,1' => array(
                '3b3b3c-99989b' => 'Black',
                'e1e4e3-d7d9d8' => 'White'
            ),
            'iPhone5,3' => array(
                '3b3b3c-faf189' => 'Yellow',
                '3b3b3c-fe767a' => 'Pink',
                '3b3b3c-f5f4f7' => 'White',
                '3b3b3c-a1e877' => 'Green',
                '3b3b3c-46abe0' => 'Blue'
            ),
            'iPhone5,4' => 'iPhone5,3',
            'iPhone6,1' => array(
                'e1e4e3-d4c5b3' => 'Gold',
                'e1e4e3-d7d9d8' => 'Silver Gray',
                '3b3b3c-99989b' => 'Space Gray'
            ),
            'iPhone6,2' => 'iPhone6,1',
            'iPhone7,1' => array(
                'e1e4e3-e1ccb5' => 'Gold',
                'e1e4e3-d7d9d8' => 'Silver Gray',
                '3b3b3c-b4b5b9' => 'Space Gray'
            ),
            'iPhone7,2' => 'iPhone7,1'
        );

        $modelColors = null;

        if (isset($colors[$model])) {
            if (is_array($colors[$model])) {
                $modelColors = $colors[$model];
            } elseif (isset($colors[$colors[$model]])) {
                $modelColors = $colors[$colors[$model]];
            }
        }

        if ($modelColors !== null && isset($modelColors[$color])) {
            return $modelColors[$color];
        }

        return false;
    }

    public function assigniCloudDevice($devId, $account, $password, $id, $userId)
    {
        $sosumi = new \CS\ICloud\Locations\Sosumi($account, $password);

        $info = $sosumi->getDeviceInfo($id);

        $usersNotes = $this->di['usersNotesProcessor'];
        $usersNotes->deviceFindMyIphoneConnected($devId, $info['deviceDisplayName'], $info['name'], $userId);
        
        $pdo = $this->di->get('db');
        $devId = $pdo->quote($devId);
        $id = $pdo->quote($id);

        $pdo->exec("UPDATE `devices_icloud` SET `location_device_hash` = {$id} WHERE `dev_id` = {$devId}");
    }

    public function autoAssigniCloudDevice($devId, $userId)
    {
        $credentials = $this->getiCloudDeviceCredentials($devId);
        
        $info = \CS\ICloud\Locations::getLocationsDeviceInfo($credentials['apple_id'], $credentials['apple_password'], $credentials['serial_number']);
        
        if ($info !== false) {
            $usersNotes = $this->di['usersNotesProcessor'];
            $usersNotes->deviceFindMyIphoneAutoConnected($devId, $info['deviceDisplayName'], $info['name'], $userId);
            
            $pdo = $this->di->get('db');
            $devId = $pdo->quote($devId);
            $locationId = $pdo->quote($info['id']);

            $pdo->exec("UPDATE `devices_icloud` SET `location_device_hash` = {$locationId} WHERE `dev_id` = {$devId}");
            return true;
        }

        return false;
    }

    public function addLocationValue($devId, $timestamp, $latitude, $longitude, $accuracy)
    {
        $uniqueHash = $this->getDb()->quote(md5('check-in' . $timestamp . $latitude . $longitude));

        $devId = $this->getDb()->quote($devId);
        $timestamp = $this->getDb()->quote($timestamp);
        $latitude = $this->getDb()->quote($latitude);
        $longitude = $this->getDb()->quote($longitude);
        $accuracy = $this->getDb()->quote($accuracy);

        $this->getDb()->exec("INSERT IGNORE INTO `geo_events` SET
                            `dev_id` = {$devId},
                            `type` = 'check-in',
                            `latitude` = {$latitude},
                            `longitude` = {$longitude},
                            `accuracy` = {$accuracy},
                            `timestamp` = {$timestamp},
                            `unique_hash` = {$uniqueHash}");
    }

}
