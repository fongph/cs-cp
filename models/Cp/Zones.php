<?php

namespace Models\Cp;

use CS\Models\GoogleMaps\StaticMap;

class Zones extends BaseModel
{

    private static $daysNames = array('SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA');
    public static $countLimit = 200;

    const TRIGGER_ENTER = 'enter';
    const TRIGGER_LEAVE = 'leave';
    const TRIGGER_BOTH = 'both';

    public function getTrigerList()
    {
        return array(
            self::TRIGGER_ENTER => 'Enter',
            self::TRIGGER_LEAVE => 'Leave',
            self::TRIGGER_BOTH => 'Both'
        );
    }

    public static function validateName($value)
    {
        $length = strlen($value);
        return $length > 0 && $length < 18;
    }

    public static function validateZoneData($value)
    {
        $parts = explode('|', $value);
        if (count($parts) !== 3) {
            return false;
        }

        return is_numeric($parts[0]) && is_numeric($parts[1]) && is_numeric($parts[2]) &&
                abs($parts[0]) <= 90 && abs($parts[1]) <= 180 && $parts[2] > 0;
    }

    public function getDeviceZonesCount($devId)
    {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT COUNT(*) FROM `geo_zones` WHERE `dev_id` = {$devId} AND `deleted` = 0")->fetchColumn();
    }

    public function addZone($devId, $zoneData, $name, $trigger, $emailAlert, $smsAlert, $scheduleData, $enable)
    {
        list($latitude, $longitude, $radius) = explode('|', $zoneData);
        $latitude = $this->getDb()->quote($latitude);
        $longitude = $this->getDb()->quote($longitude);
        $radius = $this->getDb()->quote($radius);
        $schedule = $this->getDb()->quote($scheduleData);

        $devId = $this->getDb()->quote($devId);
        $name = $this->getDb()->quote($name);
        $trigger = $this->getDb()->quote($trigger);
        $emailAlert = $emailAlert > 0 ? 1 : 0;
        $smsAlert = $smsAlert > 0 ? 1 : 0;
        $enable = $enable > 0 ? 1 : 0;

        return $this->getDb()->exec("INSERT INTO `geo_zones` SET `dev_id` = {$devId}, `latitude` = {$latitude}, `longitude` = {$longitude}, `radius` = {$radius}, `name` = {$name}, `trigger` = {$trigger}, `sms_alert` = {$smsAlert}, `email_alert` = {$emailAlert}, `enable` = {$enable}, `schedule` = {$schedule}");
    }

    public function updateZone($id, $devId, $zoneData, $name, $trigger, $emailAlert, $smsAlert, $scheduleData, $enable)
    {
        list($latitude, $longitude, $radius) = explode('|', $zoneData);
        $latitude = $this->getDb()->quote($latitude);
        $longitude = $this->getDb()->quote($longitude);
        $radius = $this->getDb()->quote($radius);
        $schedule = $this->getDb()->quote($scheduleData);

        $id = $this->getDb()->quote($id);
        $devId = $this->getDb()->quote($devId);
        $name = $this->getDb()->quote($name);
        $trigger = $this->getDb()->quote($trigger);
        $emailAlert = $emailAlert > 0 ? 1 : 0;
        $smsAlert = $smsAlert > 0 ? 1 : 0;
        $enable = $enable > 0 ? 1 : 0;

        return $this->getDb()->exec("UPDATE `geo_zones` SET `dev_id` = {$devId}, `latitude` = {$latitude}, `longitude` = {$longitude}, `radius` = {$radius}, `name` = {$name}, `trigger` = {$trigger}, `sms_alert` = {$smsAlert}, `email_alert` = {$emailAlert}, `enable` = {$enable}, `schedule` = {$schedule} WHERE `id` = {$id} LIMIT 1");
    }

    public function getZonesList($devId)
    {
        $devId = $this->getDb()->quote($devId);
        $data = $this->getDb()->query("SELECT * FROM `geo_zones` WHERE `dev_id` = {$devId} AND `deleted` = 0")->fetchAll();

        foreach ($data as $key => $value) {
            $value['icon'] = self::getZoneIconUrl($value['latitude'], $value['longitude'], $value['radius']);
            $data[$key] = $value;
        }

        return $data;
    }

    public function getDeviceZone($devId, $zoneId)
    {
        $devId = $this->getDb()->quote($devId);
        $zoneId = $this->getDb()->quote($zoneId);

        return $this->getDb()->query("SELECT * FROM `geo_zones` WHERE `id` = {$zoneId} AND `dev_id` = {$devId} AND `deleted` = 0 LIMIT 1")->fetch();
    }

    public function getLastPoint()
    {
        
    }

    public function getMapZonesList($devId)
    {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT 
                    `id`,
                    `latitude`,
                    `longitude`,
                    `radius`,
                    `name`,
                    `trigger`,
                    `email_alert`,
                    (`schedule` = '') schedule
                FROM 
                    `geo_zones` 
                WHERE 
                    `dev_id` = {$devId} AND 
                    `deleted` = 0 AND
                    `enable` = 1")->fetchAll();
    }

    public function canDeleteZone($devId, $zoneId)
    {
        $devId = $this->getDb()->quote($devId);
        $zoneId = $this->getDb()->quote($zoneId);

        return $this->getDb()->query("SELECT `id` FROM `geo_zones` WHERE `dev_id` = {$devId} AND `id` = {$zoneId} AND `deleted` = 0 LIMIT 1") !== false;
    }

    public function deleteZone($devId, $zoneId)
    {
        $devId = $this->getDb()->quote($devId);
        $zoneId = $this->getDb()->quote($zoneId);

        return $this->getDb()->exec("UPDATE `geo_zones` SET `deleted` = 1 WHERE `dev_id` = {$devId} AND `id` = {$zoneId}");
    }

    public static function getZoneIconUrl($latitude, $longitude, $radius)
    {
        return StaticMap::getImageUrlCircle($latitude, $longitude, 120, 120, $radius);
    }

    private static function scheduleValueToRecurrenceRule($value)
    {
        if (strlen($value) === 0) {
            throw new \Exception("Empty string");
        }

        $days = explode(',', $value);

        $realDays = array();

        foreach (self::$daysNames as $dayName) {
            if (in_array($dayName, $days)) {
                array_push($realDays, $dayName);
            }
        }

        if (count($realDays) === 0) {
            throw new \Exception("No days in string!");
        }

        return 'FREQ=WEEKLY;BYDAY=' . implode(',', $realDays);
    }

    private static function checkScheduleElement($value)
    {
        $parts = explode('|', $value);

        if (count($parts) !== 3) {
            throw new \Exception("Invalid count of parts!");
        }

        return implode('|', array($parts[0], $parts[1], self::scheduleValueToRecurrenceRule($parts[2])));
    }

    public static function schedulesToRecurrenceList($value)
    {
        $parts = explode('@', $value);

        $result = array();
        foreach ($parts as $value) {
            $string = self::checkScheduleElement($value);
            array_push($result, $string);
        }

        return implode('@', $result);
    }

    public static function recurrenceListToSchedules($value)
    {
        $parts = explode('@', $value);

        $result = array();
        foreach ($parts as $value) {
            if (strlen($value)) {
                list($from, $to, $rrule) = explode('|', $value);
                array_push($result, implode('|', array($from, $to, substr($rrule, strlen('FREQ=WEEKLY;BYDAY=')))));
            }
        }

        return implode('@', $result);
    }

}
