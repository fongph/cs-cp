<?php

namespace Models\Cp;

class Whatsapp extends BaseModel {

    public function getPrivateDataTableData($devId, $params = array()) {
        $devId = $this->getDb()->quote($devId);

        $search = '';
        if (!empty($params['search'])) {
            $searched = $this->getDb()->quote('%' . $params['search'] . '%');
            $search = "wm.`text` LIKE {$searched} OR wm.`number_name` LIKE {$searched}";
        }

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['wm.`number_name`', 'wm.`text`', 'wm.`timestamp`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT wm.`phone_number` id, wm.`number_name` name, LEFT(wm.`text`, 201) `text`, wm.`timestamp`";

        if (isset($params['timeFrom'], $params['timeTo'])) {
            $timeFrom = $this->getDb()->quote($params['timeFrom']);
            $timeTo = $this->getDb()->quote($params['timeTo']);
            
            $fromWhere = "FROM `whatsapp_messages` wm
                            INNER JOIN (
                                SELECT 
                                    MAX(`timestamp`) maxTimestamp,
                                    `phone_number`
                                FROM `whatsapp_messages` 
                                WHERE 
                                    `dev_id` = {$devId} AND 
                                    `group_id` IS NULL AND
                                    `timestamp` >= {$timeFrom} AND
                                    `timestamp` <= {$timeTo}
                                GROUP BY `phone_number`
                            ) wm2 ON wm.`phone_number` = wm2.`phone_number` AND wm.`timestamp` = wm2.`maxTimestamp`
                            WHERE 
                                wm.`dev_id` = {$devId} AND
                                wm.`group_id` IS NULL AND
                                wm.`timestamp` >= {$timeFrom} AND
                                wm.`timestamp` <= {$timeTo}
                            GROUP BY wm.`phone_number`";
        }

        $query = "{$select} {$fromWhere}"
                . ($search ? " AND ({$search})" : '')
                . " ORDER BY {$sort} LIMIT {$params['start']}, {$params['length']}";

        $result = array(
            'aaData' => $this->getDb()->query($query)->fetchAll(\PDO::FETCH_ASSOC)
        );

        if (empty($result['aaData'])) {
            $result['iTotalRecords'] = 0;
            $result['iTotalDisplayRecords'] = 0;
        } else {
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT wm.`phone_number` {$fromWhere}) a")->fetchColumn();

            if (strlen($search)) {
                $result['iTotalDisplayRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT wm.`phone_number` {$fromWhere} AND ({$search})) a")->fetchColumn();
            } else {
                $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
            }
        }

        return $result;
    }

    public function getGroupDataTableData($devId, $params = array()) {
        $devId = $this->getDb()->quote($devId);

        $search = '';
        if (!empty($params['search'])) {
            $searched = $this->getDb()->quote('%' . $params['search'] . '%');
            $search = "wm.`text` LIKE {$searched} OR wm.`number_name` LIKE {$searched}";
        }

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['wm.`number_name`', 'wm.`text`', 'wm.`timestamp`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT wm.`number_name` name, LEFT(wm.`text`, 201) text, wm.`timestamp`, wm.`group_id` `group`";

        if (isset($params['timeFrom'], $params['timeTo'])) {
            $timeFrom = $this->getDb()->quote($params['timeFrom']);
            $timeTo = $this->getDb()->quote($params['timeTo']);

            $fromWhere = "FROM `whatsapp_messages` wm
                INNER JOIN (
                    SELECT 
                        MAX(`timestamp`) maxTimestamp,
                        `group_id`
                    FROM `whatsapp_messages` 
                    WHERE 
                        `dev_id` = {$devId} AND 
                        `group_id` IS NOT NULL AND
                        `timestamp` >= {$timeFrom} AND
                        `timestamp` <= {$timeTo}
                    GROUP BY `group_id`
                ) wm2 ON wm.`group_id` = wm2.`group_id` AND wm.`timestamp` = wm2.`maxTimestamp`
                WHERE 
                    wm.`dev_id` = {$devId} AND
                    wm.`group_id` IS NOT NULL AND
                    wm.`timestamp` >= {$timeFrom} AND
                    wm.`timestamp` <= {$timeTo}
                GROUP BY wm.`group_id`";
        }

        $query = "{$select} {$fromWhere}"
                . ($search ? " AND ({$search})" : '')
                . " ORDER BY {$sort} LIMIT {$params['start']}, {$params['length']}";
                
        $result = array(
            'aaData' => $this->getDb()->query($query)->fetchAll(\PDO::FETCH_ASSOC)
        );

        if (empty($result['aaData'])) {
            $result['iTotalRecords'] = 0;
            $result['iTotalDisplayRecords'] = 0;
        } else {
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT wm.`group_id` {$fromWhere}) a")->fetchColumn();

            $result['ssss'] = $search;

            if (strlen($search)) {
                $result['iTotalDisplayRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT wm.`group_id` {$fromWhere} AND ({$search})) a")->fetchColumn();
            } else {
                $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
            }
        }

        return $result;
    }
    
    public function getCallsDataTableData($devId, $params = array()) {
        $devId = $this->getDb()->quote($devId);

        $search = '';
        if (!empty($params['search'])) {
            $searched = $this->getDb()->quote('%' . $params['search'] . '%');
            $search = "`phone_number` LIKE {$searched} OR `number_name` LIKE {$searched}";
        }

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['`number_name`', '`type`', '`duration`', '`timestamp`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT `number_name` name, `type`, `duration`, `timestamp`";

        $timeFrom = $this->getDb()->quote($params['timeFrom']);
        $timeTo = $this->getDb()->quote($params['timeTo']);
        $fromWhere = "FROM `whatsapp_calls` WHERE
                `dev_id` = {$devId} AND
                `timestamp` >= {$timeFrom} AND
                `timestamp` <= {$timeTo}";


        $query = "{$select} {$fromWhere}"
                . ($search ? " AND ({$search})" : '')
                . " ORDER BY {$sort} LIMIT {$params['start']}, {$params['length']}";

        $result = array(
            'aaData' => $this->getDb()->query($query)->fetchAll(\PDO::FETCH_ASSOC)
        );

        if (empty($result['aaData'])) {
            $result['iTotalRecords'] = 0;
            $result['iTotalDisplayRecords'] = 0;
        } else {
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT `dev_id` {$fromWhere}) a")->fetchColumn();
            $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
        }

        return $result;
    }
    
    public function getPrivateList($devId, $phoneNumber) {
        $devId = $this->getDb()->quote($devId);
        $phoneNumber = $this->getDb()->quote($phoneNumber);

        return $this->getDb()->query("SELECT 
                                            `type`,
                                            `number_name` name,
                                            `phone_number` phone,
                                            `text`,
                                            `timestamp`
                                        FROM `whatsapp_messages` WHERE `dev_id` = {$devId} AND `group_id` IS NULL AND `phone_number` = {$phoneNumber} ORDER BY `timestamp` DESC")->fetchAll();
    }

    public function getGroupList($devId, $groupId) {
        $devId = $this->getDb()->quote($devId);
        $groupId = $this->getDb()->quote($groupId);

        return $this->getDb()->query("SELECT 
                                            `type`,
                                            `number_name` name,
                                            `phone_number` phone,
                                            `text`,
                                            `timestamp`
                                        FROM `whatsapp_messages` WHERE `dev_id` = {$devId} AND `group_id` = {$groupId} GROUP BY `timestamp` ORDER BY `timestamp` DESC")->fetchAll();
    }

    public function getGroupUsers($devId, $groupId) {
        $devId = $this->getDb()->quote($devId);
        $groupId = $this->getDb()->quote($groupId);

        return $this->getDb()->query("SELECT `phone_number`, `number_name` FROM `whatsapp_messages` WHERE `dev_id` = {$devId} AND `group_id` = {$groupId} ORDER BY `number_name`")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
    
    public function hasRecords($devId) {
        $devId = $this->getDb()->quote($devId);
        
        return $this->getDb()->query("SELECT `dev_id` FROM `whatsapp_messages` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn() !== false;
    }
    
    public function getLastTimestamp($devId) {
        $devId = $this->getDb()->quote($devId);
        return $this->getDb()->query("SELECT `w`.`timestamp` as `timestamp` FROM 
                ( SELECT `timestamp` FROM `whatsapp_messages` WHERE `dev_id` = {$devId} 
                  UNION 
                  SELECT `timestamp` FROM `whatsapp_calls` WHERE `dev_id` = {$devId}) as `w` 
                WHERE 1
                GROUP BY `w`.`timestamp` 
                ORDER BY `w`.`timestamp` DESC 
                LIMIT 1")->fetch();
    }
}
