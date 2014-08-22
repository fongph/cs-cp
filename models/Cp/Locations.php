<?php

namespace Models\Cp;

class Locations extends \System\Model {

    public function getPoints($devId, $date) {
        if (!$this->_validateDate($date)) {
            return false;
        }
        
        $devId = $this->getDb()->quote($devId);
        $timeFrom = $this->_getDateTimestamp($date);
        $timeTo = $timeFrom + 24 * 3600;
        return $this->getDb()->query("SELECT `address`, TRIM(TRAILING '0' FROM `latitude`) as latitude, TRIM(TRAILING '0' FROM `longitude`) as longitude, `accuracy`, `timestamp` FROM `gps_log` WHERE `dev_id` = {$devId} AND `timestamp` BETWEEN {$timeFrom} AND {$timeTo} ORDER BY `timestamp`")->fetchAll(\PDO::FETCH_NUM);
    }

    public function getLastPointTime($devId){
        $devId = $this->getDb()->quote($devId);
        
        return $this->getDb()->query("SELECT MAX(`timestamp`) FROM `gps_log` WHERE `dev_id` = {$devId}")->fetchColumn();
    }
    
    protected function _validateDate($date) {
        $parts = explode('-', $date);
        return (count($parts) === 3) && checkdate($parts[0], $parts[1], $parts[2]);
    }

    protected function _getDateTimestamp($dateString) {
        $parts = explode('-', $dateString);
        return mktime(0, 0, 0, $parts[0], $parts[1], $parts[2]);
    }
}