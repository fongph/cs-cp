<?php

namespace Models\Cp;

class Viber extends BaseModel {

    public function getPrivateDataTableData($devId, $params = array()) {
        $devId = $this->getDb()->quote($devId);

        $search = '';
        if (!empty($params['search'])) {
            $searched = $this->getDb()->quote('%' . $params['search'] . '%');
            $search = "vm.`text` LIKE {$searched} OR vm.`number_name` LIKE {$searched}";
        }

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['vm.`number_name`', 'vm.`text`', 'vm.`timestamp`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT vm.`phone_number` id, vm.`number_name` name, LEFT(vm.`text`, 201) `text`, vm.`timestamp`";


        $timeFrom = $this->getDb()->quote($params['timeFrom']);
        $timeTo = $this->getDb()->quote($params['timeTo']);

        $fromWhere = "FROM `viber_messages` vm
                            INNER JOIN (
                                SELECT 
                                    MAX(`timestamp`) maxTimestamp,
                                    `phone_number`
                                FROM `viber_messages` 
                                WHERE 
                                    `dev_id` = {$devId} AND 
                                    `group_id` IS NULL AND
                                    `timestamp` >= {$timeFrom} AND
                                    `timestamp` <= {$timeTo}
                                GROUP BY `phone_number`
                            ) vm2 ON vm.`phone_number` = vm2.`phone_number` AND vm.`timestamp` = vm2.`maxTimestamp`
                            WHERE 
                                vm.`dev_id` = {$devId} AND
                                vm.`group_id` IS NULL AND
                                vm.`timestamp` >= {$timeFrom} AND
                                vm.`timestamp` <= {$timeTo}
                            GROUP BY vm.`phone_number`";

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
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT vm.`phone_number` {$fromWhere}) a")->fetchColumn();

            if (strlen($search)) {
                $result['iTotalDisplayRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT vm.`phone_number` {$fromWhere} AND ({$search})) a")->fetchColumn();
            } else {
                $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
            }
        }

        return $result;
    }

    public function getGroupDataTableData($devId, $params = array()) {
        if (!$devId)
            return null;

        $devId = $this->getDb()->quote($devId);

        $search = '';
        if (!empty($params['search'])) {
            $searched = $this->getDb()->quote('%' . $params['search'] . '%');
            $search = "vm.`text` LIKE {$searched} OR vm.`number_name` LIKE {$searched}";
        }

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['vm.`number_name`', 'vm.`text`', 'vm.`timestamp`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT vm.`number_name` name, LEFT(vm.`text`, 201) text, vm.`timestamp`, vm.`group_id` `group`";


        $timeFrom = $this->getDb()->quote($params['timeFrom']);
        $timeTo = $this->getDb()->quote($params['timeTo']);

        $fromWhere = "FROM `viber_messages` vm
                INNER JOIN (
                    SELECT 
                        MAX(`timestamp`) maxTimestamp,
                        `group_id`
                    FROM `viber_messages` 
                    WHERE 
                        `dev_id` = {$devId} AND 
                        `group_id` IS NOT NULL AND
                        `timestamp` >= {$timeFrom} AND
                        `timestamp` <= {$timeTo}
                    GROUP BY `group_id`
                ) vm2 ON vm.`group_id` = vm2.`group_id` AND vm.`timestamp` = vm2.`maxTimestamp`
                WHERE 
                    vm.`dev_id` = {$devId} AND
                    vm.`group_id` IS NOT NULL AND
                    vm.`timestamp` >= {$timeFrom} AND
                    vm.`timestamp` <= {$timeTo}
                GROUP BY vm.`group_id`";


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
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT vm.`group_id` {$fromWhere}) a")->fetchColumn();

            $result['ssss'] = $search;

            if (strlen($search)) {
                $result['iTotalDisplayRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT vm.`group_id` {$fromWhere} AND ({$search})) a")->fetchColumn();
            } else {
                $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
            }
        }

        return $result;
    }

    public function getCallsDataTableData($devId, $params = array()) {
        if (!$devId)
            return null;

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

        $select = "SELECT `number_name`, `type`, `duration`, `timestamp`";

        $timeFrom = $this->getDb()->quote($params['timeFrom']);
        $timeTo = $this->getDb()->quote($params['timeTo']);
        $fromWhere = "FROM `viber_calls` WHERE `dev_id` = {$devId} AND `timestamp` >= {$timeFrom} AND `timestamp` <= {$timeTo}";

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

    public function getPrivateList($devId, $phoneNumber) {
        $devId = $this->getDb()->quote($devId);
        $phoneNumber = $this->getDb()->quote($phoneNumber);

        return $this->getDb()->query("SELECT 
                                            `type`,
                                            `number_name` name,
                                            `phone_number` phone,
                                            `text`,
                                            `timestamp`
                                        FROM `viber_messages` WHERE `dev_id` = {$devId} AND `group_id` IS NULL AND `phone_number` = {$phoneNumber} ORDER BY `timestamp` DESC")->fetchAll();
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
                                        FROM `viber_messages` WHERE `dev_id` = {$devId} AND `group_id` = {$groupId} GROUP BY `timestamp` ORDER BY `timestamp` DESC")->fetchAll();
    }

    public function getGroupUsers($devId, $groupId) {
        $devId = $this->getDb()->quote($devId);
        $groupId = $this->getDb()->quote($groupId);

        return $this->getDb()->query("SELECT `phone_number`, `number_name` FROM `viber_messages` WHERE `dev_id` = {$devId} AND `group_id` = {$groupId} ORDER BY `number_name`")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
    
    public function hasRecords($devId) {
        $devId = $this->getDb()->quote($devId);
        
        return $this->getDb()->query("SELECT `dev_id` FROM `viber_messages` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn() !== false;
    }

}
