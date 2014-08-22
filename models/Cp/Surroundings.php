<?php

namespace Models\Cp;

class Surroundings extends \System\Model {

    public static $_maxSurroundingLength = 1800;
    private static $_authLifeTime = 3600;
    
    const STATUS_AWAITING_SEND = 1;
    const STATUS_AWAITING_RECORD = 2;
    const STATUS_AWAITING_UPLOAD = 3;
    const STATUS_UPLOADED = 4;
    const STATUS_CONVERTED = 5;
    const STATUS_COMPLETE = 6;

    public function getPlayUrl($devId, $timestamp) {
        $s3 = $this->di->get('S3');
        return $s3->getAuthenticatedURL($this->di['config']['s3']['bucket'], $devId . '/surrounding/' . $timestamp . '.mp3', self::$_authLifeTime);
    }
    
    public function getDownloadUrl($devId, $timestamp) {
        $s3 = $this->di->get('S3');
        return $s3->getAuthenticatedURL($this->di['config']['s3']['bucket'], $devId . '/surrounding/' . $timestamp . '.mp3', self::$_authLifeTime, false, false, array(
            'response-content-disposition' => 'attachment; filename=' . $timestamp . '.mp3'
        ));
    }
    
    public function getDataTableData($devId, $params = array()) {
        $devId = $this->getDb()->quote($devId);

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['`timestamp_start`', '`duration`', '`status`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT `timestamp_start` timestamp, `duration`, `status`, `dynamic_status` dstatus, `error`";

        $fromWhere = "FROM `surroundings` WHERE `dev_id` = {$devId}";


        $query = "{$select} {$fromWhere}"
                . " ORDER BY {$sort} LIMIT {$params['start']}, {$params['length']}";

        $result = array(
            'aaData' => $this->getDb()->query($query)->fetchAll(\PDO::FETCH_ASSOC)
        );

        if (empty($result['aaData'])) {
            $result['iTotalRecords'] = 0;
            $result['iTotalDisplayRecords'] = 0;
        } else {
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) {$fromWhere}")->fetchColumn();
            $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
        }

        return $result;
    }

    public function getTimeDiff($devId) {
        $devId = $this->getDB()->quote($devId);

        return $this->getDb()->query("SELECT `timediff` FROM `user_dev` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn();
    }

    public function getSurroundingLimit($devId) {
        $devId = $this->getDB()->quote($devId);

        return $this->getDb()->query("SELECT `surr_record` FROM `limitations` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn();
    }

    public function getActiveIntervals($devId) {
        $devId = $this->getDB()->quote($devId);

        return $this->getDb()->query("SELECT `timestamp_start` start, (`timestamp_start` + `duration`) end FROM `surroundings` WHERE `dev_id` = {$devId}")->fetchAll();
    }

    public function getMaxAwaitingTime($devId) {
        return time() - $this->getTimeDiff($devId) + 7200;
    }

    public function addSurroundingTask($devId, $start, $duration) {
        $minTime = time() - $this->getTimeDiff($devId) + 18 * 60;
        $duration = intval($duration) * 60;
        $start = intval($start);
        
        if ($start < $minTime) {
            throw new SurroundingStartTimeInvalidException();
        }

        if ($duration < 1 || $duration > self::$_maxSurroundingLength) {
            throw new SurroundingDurationInvalidException();
        }

        $activeRecords = $this->getActiveIntervals($devId);

        foreach ($activeRecords as $item) {
            if (($item["start"] <= $start && $item['end'] >= $start) ||
                    ($item["start"] >= $start and $item["start"] <= $start + $duration)) {

                throw new SurroundingIntervalInvalidException();
            }
        }

        if (!$this->getSurroundingLimit($devId)) {
            throw new SurroundingLimitReachedException();
        }

        $devId = $this->getDB()->quote($devId);

        $startStatus = self::STATUS_AWAITING_SEND;
        
        $this->getDb()->exec("UPDATE `limitations` SET `surr_record` = (`surr_record` - 1) WHERE `dev_id` = {$devId}");
        return $this->getDb()->exec("INSERT INTO `surroundings` SET `dev_id` = {$devId}, `timestamp_start` = {$start}, `duration` = {$duration}, `dynamic_status` = {$startStatus}, `error` = 0, `status` = 0");
    }

    //@TODO: delete files
    public function delete($devId, $start) {
        $devId = $this->getDB()->quote($devId);
        $start = $this->getDB()->quote($start);
        
        return $this->getDb()->exec("DELETE FROM `surroundings` WHERE `dev_id` = {$devId} AND `timestamp_start` = {$start}");
    }
    
    public function hasRecords($devId) {
        $devId = $this->getDb()->quote($devId);
        
        return $this->getDb()->query("SELECT `dev_id` FROM `surroundings` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn() !== false;
    }
}

class SurroundingStartTimeInvalidException extends \Exception {
    
}

class SurroundingDurationInvalidException extends \Exception {
    
}

class SurroundingIntervalInvalidException extends \Exception {
    
}

class SurroundingLimitReachedException extends \Exception {
    
}
