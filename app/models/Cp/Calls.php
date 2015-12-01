<?php

namespace Models\Cp;

class Calls extends BaseModel {

    public function getDataTableData($devId, $params = array()) {
        $devId = $this->getDb()->quote($devId);

        $search = '';
        if (!empty($params['search'])) {
            $searched = $this->getDb()->quote('%' . $params['search'] . '%');
            $search = "`phone_number` LIKE {$searched} OR `number_name` LIKE {$searched}";
        }

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['`timestamp`', '`call_type`', '`phone_number`', '`number_name`', '`duration`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT `timestamp`, `call_type`, `phone_number`, `number_name`, `duration`";

        if ($params['timeFrom'] > 0 && $params['timeTo'] > 0) {
            $timeFrom = $this->getDb()->quote($params['timeFrom']);
            $timeTo = $this->getDb()->quote($params['timeTo']);
            $fromWhere = "FROM `call_log` WHERE `dev_id` = {$devId} AND `timestamp` >= {$timeFrom} AND `timestamp` <= {$timeTo}";
        } else {
            $fromWhere = "FROM `call_log` WHERE `dev_id` = {$devId}";
        }

        $query = "{$select} {$fromWhere}"
                . ($search ? " AND ({$search})" : '')
                . " ORDER BY {$sort} LIMIT {$params['start']}, {$params['length']}";

        $result = array(
            'aaData' => $this->getDb()->query($query)->fetchAll(\PDO::FETCH_NUM)
        );

        if (empty($result['aaData'])) {
            $result['iTotalRecords'] = 0;
            $result['iTotalDisplayRecords'] = 0;
        } else {
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) {$fromWhere}")->fetchColumn();

            if ($search) {
                $result['iTotalDisplayRecords'] = $this->getDb()->query("SELECT COUNT(*) {$fromWhere} AND ({$search})")->fetchColumn();
            } else {
                $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
            }
        }

        return $result;
    }

    public function hasRecords($devId) {
        $devId = $this->getDb()->quote($devId);
        return $this->getDb()->query("SELECT `dev_id` FROM `call_log` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn() !== false;
    }

    public function getLastTimestamp($devId) {
        $devId = $this->getDb()->quote($devId);
        return $this->getDb()->query("SELECT `timestamp` FROM `call_log` WHERE `dev_id` = {$devId} GROUP BY `timestamp` ORDER BY `timestamp` DESC LIMIT 1")->fetch();
    }
    
    public function getBlackList($devId) {
        $devId = $this->getDB()->quote($devId);

        $string = $this->getDb()->query("SELECT `bl_phones` FROM `dev_settings` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn();
        $array = explode(',', $string);

        foreach ($array as $key => $value) {
            if (!strlen($value)) {
                unset($array[$key]);
            }
        }

        return $array;
    }

}
