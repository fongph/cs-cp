<?php

namespace Models\Cp;

class Zones extends BaseModel
{

    private static $googleMapsScales = array(
        10000 => 10,
        4000 => 11,
        2000 => 12,
        1000 => 13,
        500 => 14,
        0 => 15
    );

    private static $zoneIconSize = [100, 100];
    
    const TRIGER_ENTER = 'enter';
    const TRIGER_LEAVE = 'leave';
    const TRIGER_BOTH = 'both';

    public function getTrigerList()
    {
        return array(
            self::TRIGER_ENTER => 'Enter',
            self::TRIGER_LEAVE => 'Leave',
            self::TRIGER_BOTH => 'Both'
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
                abs($parts[0]) <= 90 && $parts[1] >= 0 && $parts[1] <= 180 && $parts[2] > 0;
    }

    public function addZone($devId, $zoneData, $name, $trigger, $emailAlert, $smsAlert, $enable)
    {
        list($latitude, $longitude, $radius) = explode('|', $zoneData);
        $latitude = $this->getDb()->quote($latitude);
        $longitude = $this->getDb()->quote($longitude);
        $radius = $this->getDb()->quote($radius);

        $devId = $this->getDb()->quote($devId);
        $name = $this->getDb()->quote($name);
        $trigger = $this->getDb()->quote($trigger);
        $emailAlert = $emailAlert > 0 ? 1 : 0;
        $smsAlert = $smsAlert > 0 ? 1 : 0;
        $enable = $enable > 0 ? 1 : 0;

        return $this->getDb()->exec("INSERT INTO `geo_zones` SET `dev_id` = {$devId}, `latitude` = {$latitude}, `longitude` = {$longitude}, `radius` = {$radius}, `name` = {$name}, `trigger` = {$trigger}, `sms_alert` = {$smsAlert}, `email_alert` = {$emailAlert}, `enable` = {$enable}");
    }
    
    public function updateZone($id, $devId, $zoneData, $name, $trigger, $emailAlert, $smsAlert, $enable)
    {
        list($latitude, $longitude, $radius) = explode('|', $zoneData);
        $latitude = $this->getDb()->quote($latitude);
        $longitude = $this->getDb()->quote($longitude);
        $radius = $this->getDb()->quote($radius);
        
        $id = $this->getDb()->quote($id);
        $devId = $this->getDb()->quote($devId);
        $name = $this->getDb()->quote($name);
        $trigger = $this->getDb()->quote($trigger);
        $emailAlert = $emailAlert > 0 ? 1 : 0;
        $smsAlert = $smsAlert > 0 ? 1 : 0;
        $enable = $enable > 0 ? 1 : 0;

        return $this->getDb()->exec("UPDATE `geo_zones` SET `dev_id` = {$devId}, `latitude` = {$latitude}, `longitude` = {$longitude}, `radius` = {$radius}, `name` = {$name}, `trigger` = {$trigger}, `sms_alert` = {$smsAlert}, `email_alert` = {$emailAlert}, `enable` = {$enable} WHERE `id` = {$id} LIMIT 1");
    }
    
    public function getZonesList($devId) {
        $devId = $this->getDb()->quote($devId);
        $data = $this->getDb()->query("SELECT * FROM `geo_zones` WHERE `dev_id` = {$devId} AND `deleted` = 0")->fetchAll();
        
        foreach ($data as $key => $value) {
            $value['icon'] = self::getZoneIconUrl($value['latitude'], $value['longitude'], $value['radius']);
            $data[$key] = $value;
        }
        
        return $data;
    }
    
    public function getDeviceZone($devId, $zoneId){
        $devId = $this->getDb()->quote($devId);
        $zoneId = $this->getDb()->quote($zoneId);
        
        return $this->getDb()->query("SELECT * FROM `geo_zones` WHERE `id` = {$zoneId} AND `dev_id` = {$devId} AND `deleted` = 0 LIMIT 1")->fetch();
    }

    public function getLastPoint()
    {
        
    }
    
    public function canDeleteZone($devId, $zoneId) {
        $devId = $this->getDb()->quote($devId);
        $zoneId = $this->getDb()->quote($zoneId);
        
        return $this->getDb()->query("SELECT `id` FROM `geo_zones` WHERE `dev_id` = {$devId} AND `id` = {$zoneId} AND `deleted` = 0 LIMIT 1") !== false;
    }
    
    public function deleteZone($devId, $zoneId) {
        $devId = $this->getDb()->quote($devId);
        $zoneId = $this->getDb()->quote($zoneId);
        
        return $this->getDb()->exec("UPDATE `geo_zones` SET `deleted` = 1 WHERE `dev_id` = {$devId} AND `id` = {$zoneId}");
    }

    public static function getZoneIconUrl($latitude, $longitude, $radius)
    {
        return 'http://maps.googleapis.com/maps/api/staticmap?center=' . 
            $latitude . ',' . $longitude . '&zoom=' . 
            self::getGoogleMapsZoom($radius) . 
            '&size=' . implode('x', self::$zoneIconSize) . '&sensor=false';
    }

    public static function getGoogleMapsZoom($radius)
    {
        $max = null;
        foreach (self::$googleMapsScales as $minRadius => $zoom) {
            if (($radius >= $minRadius) && ($max === null || $minRadius > $max)) {
                $max = $minRadius;
            }
        }

        if ($max === null) {
            return 1;
        }

        return self::$googleMapsScales[$max];
    }

}
