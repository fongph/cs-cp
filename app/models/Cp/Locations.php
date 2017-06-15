<?php

namespace Models\Cp;

use CS\ICloud\Locations as LocationsService;
use Components\CloudDeviceManager\AppleCloudDeviceManager;

class Locations extends BaseModel {

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

    public function getPointsForExport($deviceId, $pointList, $timeFrom, $timeTo)
    {
        if (isset($timeFrom, $timeTo) &&
                is_numeric($timeFrom) && is_numeric($timeTo) &&
                $timeFrom >= 0 && $timeTo >= $timeFrom) {

            $from = $this->getDb()->quote($timeFrom);
            $to = $this->getDb()->quote($timeTo);
            $selectRange = "AND ge.`timestamp` BETWEEN {$from} AND {$to}";
        } else {
            $selectRange = '';
        }

        $id = $this->getDb()->quote($deviceId);
        $fences = [];
        foreach ($pointList as $item) {
            $fences[] = $this->getDb()->quote($item);
        }
        $zones = implode(',', $fences);

        return $this->getDb()->query("SELECT
                ge.`timestamp`,
                ge.`type`,
                ge.`address`,
                gz.`id` as zone_id,
                gz.`name` as zone,
                TRIM(TRAILING '0' FROM ge.`latitude`) as latitude,
                TRIM(TRAILING '0' FROM ge.`longitude`) as longitude,                
                ge.`email_notified`,
                ge.`sms_notified`
            FROM `geo_events` ge
            LEFT JOIN `geo_zones` gz ON gz.`id` = ge.`zone_id`
            WHERE 
                ge.`dev_id` = {$id} AND
                zone_id IN ({$zones}) 
                {$selectRange}
            ORDER BY `timestamp`")->fetchAll(\PDO::FETCH_ASSOC);
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

        return $this->getDb()->query("SELECT  `latitude`, `longitude`, `radius`, `name` FROM `geo_zones` WHERE `dev_id` = {$devId} AND `deleted` = 0 AND `enable` = 1")->fetchAll();
    }

    public function getZonesNames($devId)
    {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT `id`, `name` FROM `geo_zones` WHERE `dev_id` = {$devId} AND `deleted` = 0 AND `enable` = 1")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function getCloudDeviceCredentials($devId)
    {
        $pdo = $this->di->get('db');
        $devId = $pdo->quote($devId);

        return $pdo->query("SELECT 
                                di.`apple_id`,
                                di.`apple_password`,
                                d.`unique_id` serial_number,
                                d.`token`
                            FROM `devices_icloud` di
                            INNER JOIN `devices` d ON d.`id` = di.`dev_id`
                            WHERE
                                di.`dev_id` = {$devId} LIMIT 1")->fetch();
    }

    public function getDeviceLocationServiceCredentials($deviceId)
    {
        $pdo = $this->di->get('db');

        $id = $pdo->quote($deviceId);
        return $pdo->query("SELECT 
                di.`apple_id`, 
                di.`apple_password`, 
                di.`location_device_hash`, 
                di.`fmip_disabled`,
                d.`unique_id` as serial_number
            FROM `devices_icloud` di
            INNER JOIN `devices` d ON d.id = di.dev_id
            WHERE 
                di.`dev_id` = {$id} AND
                di.`location_device_hash` != ''
            LIMIT 1")->fetch();
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

    public function assigniCloudDevice($deviceId, $appleId, $password, $id, $userId)
    {
        $device = $this->getDeviceLocation($appleId, $password, $id);

        if ($device == false) {
            throw new \Exception("Invalid id passed");
        }

        $usersNotes = $this->di['usersNotesProcessor'];
        $usersNotes->deviceFindMyIphoneConnected($deviceId, $device['deviceModelName'], $device['name'], $userId);

        $pdo = $this->di->get('db');
        $devId = $pdo->quote($deviceId);
        $id = $pdo->quote($id);

        $pdo->exec("UPDATE `devices_icloud` SET `location_device_hash` = {$id} WHERE `dev_id` = {$devId}");
    }

    public function autoAssigniCloudDevice($devId, $userId)
    {
        $credentials = $this->getCloudDeviceCredentials($devId);
        
        $device = $this->getLocationBySerialNumber($credentials['apple_id'], $credentials['apple_password'], $credentials['serial_number']);
        
        $usersNotes = $this->di['usersNotesProcessor'];
        $usersNotes->deviceFindMyIphoneAutoConnected($devId, $device['deviceModelName'], $device['name'], $userId);

        $pdo = $this->di->get('db');
        $devId = $pdo->quote($devId);
        $locationId = $pdo->quote($device['id']);

        $pdo->exec("UPDATE `devices_icloud` SET `location_device_hash` = {$locationId} WHERE `dev_id` = {$devId}");
        return true;
    }

    public function setFmipDisabled($deviceId, $value = true)
    {
        $pdo = $this->di->get('db');
        $deviceId = $pdo->quote($deviceId);

        $newValue = 1;
        if (!$value) {
            $newValue = 0;
        }

        $pdo->exec("UPDATE `devices_icloud` SET `fmip_disabled` = {$newValue} WHERE dev_id = {$deviceId} LIMIT 1");
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

    public function canMakeCloudLocationRequest($userId, $deviceId)
    {
        $value = $this->di['usersManager']->getUserOption($userId, 'device-' . $deviceId . '-cloud-location-request-time');

        if ($value == false) {
            return true;
        }
        // can make cloud location request once in 1 minute
        return (time() - $value > 60);
    }

    public function updateCloudLocationRequestTime($userId, $deviceId)
    {
        $this->di['usersManager']->setUserOption($userId, 'device-' . $deviceId . '-cloud-location-request-time', time(), \CS\Models\User\Options\UserOptionRecord::SCOPE_CONTROL_PANEL);
    }

    private function getLocationBySerialNumber($login, $password, $serialNumber)
    {
        /**
         * @var AppleCloudDeviceManager Description
         */
        $cloudDeviceManager = $this->di['cloudDeviceManager'];
        $cloudDeviceManager->getState()
                ->setAppleId($login)
                ->setApplePassword($password);

        $cloudDeviceManager->authenticate();
        return $cloudDeviceManager->getDeviceLocation($serialNumber);
    }

    public function getDevicesList($login, $password)
    {
        /**
         * @var AppleCloudDeviceManager Description
         */
        $cloudDeviceManager = $this->di['cloudDeviceManager'];
        $cloudDeviceManager->getState()
                ->setAppleId($login)
                ->setApplePassword($password);

        $cloudDeviceManager->authenticate();

        $result = [];
        foreach ($cloudDeviceManager->getDevicesLocations() as $item) {
            $batteryLevel = 'Unknown';
            
            if (is_numeric($item['batteryLevel'])) {
                $batteryLevel = ceil($item['batteryLevel'] * 100);
            }
            
            array_push($result, array(
                'id' => $item['id'],
                'name' => $item['name'],
                'model' => $item['deviceModelName'],
                'batteryLevel' => $batteryLevel,
                'сharging' => ($item['batteryStatus'] == 'Charging')
            ));
        }

        return $result;
    }

    public function getDeviceLocation($login, $password, $id)
    {
        /**
         * @var AppleCloudDeviceManager Description
         */
        $cloudDeviceManager = $this->di['cloudDeviceManager'];
        $cloudDeviceManager->getState()
                ->setAppleId($login)
                ->setApplePassword($password);

        $cloudDeviceManager->authenticate();

        foreach ($cloudDeviceManager->getDevicesLocations() as $item) {
            if ($item['id'] == $id) {
                return $item;
            }
        }

        return false;
    }

    public function getCloudLocation($userId, $devId, $credentials)
    {
        if ($credentials == false) {
            return ['success' => false, 'type' => 'undefined'];
        }

        if (!$this->canMakeCloudLocationRequest($userId, $devId)) {
            return ['success' => false, 'message' => 'You can request the location update once in a minute. Please, try again a bit later.'];
        }

        try {
            $response = $this->getDeviceLocation($credentials['apple_id'], $credentials['apple_password'], $credentials['location_device_hash']);

            if (!$response['locationEnabled']) {
                return ['success' => false, 'type' => 'location-disabled'];
            }

            if (!is_array($response['location'])) {
                return ['success' => false, 'type' => 'no-location-data'];
            }

            $data = [
                "success" => true,
                "latitude" => $response['location']['latitude'],
                "longitude" => $response['location']['longitude'],
                "accuracy" => $response['location']['accuracy'],
                "timestamp" => ceil($response['location']['timestamp'] / 1000)
            ];

            $this->updateCloudLocationRequestTime($userId, $devId);
            $this->addLocationValue($devId, $data['timestamp'], $data['latitude'], $data['longitude'], $data['accuracy']);

            return $data;
        } catch (\Components\CloudDeviceManager\Exception\DeviceLocationNotFoundException $e) {
            return ['success' => false, 'type' => 'location-disabled'];
        } catch (\Components\CloudDeviceManager\Exception\BadCredentialsException $e) {
            return [
                'success' => false,
                'message' => $this->di['t']->_('iCloud Authorization Error. Please %1$schange the password%2$s and try again.', [
                    '<a href="/profile/iCloudAccount/' . $this->di['devId'] . '">',
                    '</a>'
                ])
            ];
        } catch (\Components\CloudDeviceManager\Exception\AccountLockedException $e) {
            return [
                'success' => false,
                'message' => $this->di['t']->_('Find My iPhone Authentication error. To continue using "Locations" feature, please, unblock the target Apple ID and %1$svalidate iCloud account in our system%2$s.', [
                    '<a href="/profile/iCloudAccount/' . $this->di['devId'] . '">',
                    '</a>'
                ])
            ];
        } catch (\Exception $e) {
            $this->getDI()->get('logger')->addError('iCloud location fail!', array('exception' => $e));
            return ['success' => false, 'type' => 'undefined'];
        }
    }

}
