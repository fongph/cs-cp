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

        if ($params['timeFrom'] > 0 && $params['timeTo'] > 0) {
            $timeQuery = "`timestamp` >= {$timeFrom} AND `timestamp` <= {$timeTo}";
        } else {
            $timeQuery = "1";
        }
        
        $fromWhere = "FROM `viber_messages` vm
                            INNER JOIN (
                                SELECT 
                                    MAX(`timestamp`) maxTimestamp,
                                    `phone_number`
                                FROM `viber_messages` 
                                WHERE 
                                    `dev_id` = {$devId} AND 
                                    `group_id` IS NULL AND
                                    {$timeQuery}
                                GROUP BY `phone_number`
                            ) vm2 ON vm.`phone_number` = vm2.`phone_number` AND vm.`timestamp` = vm2.`maxTimestamp`
                            WHERE 
                                vm.`dev_id` = {$devId} AND
                                vm.`group_id` IS NULL AND
                                {$timeQuery}
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

        if ($params['timeFrom'] > 0 && $params['timeTo'] > 0) {
            $timeQuery = "`timestamp` >= {$timeFrom} AND `timestamp` <= {$timeTo}";
        } else {
            $timeQuery = "1";
        }
        
        $fromWhere = "FROM `viber_messages` vm
                INNER JOIN (
                    SELECT 
                        MAX(`timestamp`) maxTimestamp,
                        `group_id`
                    FROM `viber_messages` 
                    WHERE 
                        `dev_id` = {$devId} AND 
                        `group_id` IS NOT NULL AND
                        {$timeQuery}
                    GROUP BY `group_id`
                ) vm2 ON vm.`group_id` = vm2.`group_id` AND vm.`timestamp` = vm2.`maxTimestamp`
                WHERE 
                    vm.`dev_id` = {$devId} AND
                    vm.`group_id` IS NOT NULL AND
                    {$timeQuery}
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
        
        if ($params['timeFrom'] > 0 && $params['timeTo'] > 0) {
            $fromWhere = "FROM `viber_calls` WHERE `dev_id` = {$devId} AND `timestamp` >= {$timeFrom} AND `timestamp` <= {$timeTo}";
        } else {
            $fromWhere = "FROM `viber_calls` WHERE `dev_id` = {$devId}";
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

    public function getNumberName($devId, $phoneNumber) {
        $devId = $this->getDb()->quote($devId);
        $phoneNumber = $this->getDb()->quote($phoneNumber);

        return $this->getDb()->query("SELECT `number_name` FROM `viber_messages` WHERE `dev_id` = {$devId} AND `group_id` IS NULL AND `phone_number` = {$phoneNumber} ORDER BY `timestamp` DESC LIMIT 1")->fetchColumn();
    }

    public function isGroupDialogueExists($devId, $groupId) {
        $devId = $this->getDb()->quote($devId);
        $groupId = $this->getDb()->quote($groupId);

        return $this->getDb()->query("SELECT `id` FROM `viber_messages` WHERE `dev_id` = {$devId} AND `group_id` = {$groupId}")->fetchColumn() !== false;
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
    
     /**
     * Paginate
     */
    public function getItemsPrivateList($devId, $phoneNumber, $search, $page = 0, $length = 10) {
        $where = array();
        $escapedDevId = $this->getDb()->quote($devId);
        $escapedPhoneNumber = $this->getDb()->quote($phoneNumber);
        
        $start = ($page <= 0 ) ?  0 : $page - 1;  
        $start *= $length;
        
        $sSearch = "";
        if (!empty($search)) {
            $escapedSearch = $this->getDb()->quote("%{$search}%");
            $sSearch = "(`text` LIKE {$escapedSearch})"; // `phone_number` LIKE {$escapedSearch} OR `number_name` LIKE {$escapedSearch} OR
            $where[] = $sSearch;
        }
        if (count($where) > 0)
            $where = 'AND ' . implode(' AND ', $where);
        else
            $where = '';
        
        $list['items'] = $this->getDb()->query("SELECT 
                                            `type`,
                                            `number_name` name,
                                            `phone_number` phone,
                                            `text`,
                                            `timestamp`
                                        FROM `viber_messages` 
                                        WHERE `dev_id` = {$escapedDevId} AND `group_id` IS NULL AND `phone_number` = {$escapedPhoneNumber} 
                                        {$where}        
                                        ORDER BY `timestamp` DESC LIMIT {$start}, {$length}")->fetchAll(); 
       
        $count = $this->getCountItemsPrivateList($devId, $phoneNumber, $search);
        $list['totalPages'] = ($count) ? ceil($count/$length) : false;
        $list['countEnteres'] = (!empty($search)) ? $this->getCountItemsPrivateList($devId, $phoneNumber, false) : 0;
        $list['countItem'] = $count;
        
        return $list;
    }

    public function getCountItemsPrivateList($devId, $phoneNumber, $search) {
        $where = array();
        $escapedDevId = $this->getDb()->quote($devId);
        $escapedPhoneNumber = $this->getDb()->quote($phoneNumber);
        
        $sSearch = "";
        if (!empty($search)) {
            $escapedSearch = $this->getDb()->quote("%{$search}%");
            $sSearch = "(`text` LIKE {$escapedSearch})"; // `phone_number` LIKE {$escapedSearch} OR `number_name` LIKE {$escapedSearch} OR
            $where[] = $sSearch;
        }
        if (count($where) > 0)
            $where = 'AND ' . implode(' AND ', $where);
        else
            $where = '';
        
        $count = $this->getDb()->query("SELECT COUNT(`id`) as count FROM `viber_messages` WHERE `dev_id` = {$escapedDevId} AND `group_id` IS NULL AND `phone_number` = {$escapedPhoneNumber} {$where} ORDER BY `timestamp` DESC")->fetch();
        return ($count['count']) ? $count['count'] : false;
    }
    
    public function getItemsGroupList($devId, $groupId, $search, $page = 0, $length = 10) {
        $where = array();
        $escapedDevId = $this->getDb()->quote($devId);
        $escapedGroupId = $this->getDb()->quote($groupId);
        
        $start = ($page <= 0 ) ?  0 : $page - 1;  
        $start *= $length;
        
        $sSearch = "";
        if (!empty($search)) {
            $escapedSearch = $this->getDb()->quote("%{$search}%");
            $sSearch = "(`text` LIKE {$escapedSearch})"; // `phone_number` LIKE {$escapedSearch} OR `number_name` LIKE {$escapedSearch} OR
            $where[] = $sSearch;
        }
        if (count($where) > 0)
            $where = 'AND ' . implode(' AND ', $where);
        else
            $where = '';
        
        $list['items'] = $this->getDb()->query("SELECT 
                                            `type`,
                                            `number_name` name,
                                            `phone_number` phone,
                                            `text`,
                                            `timestamp`
                                        FROM `viber_messages` 
                                        WHERE `dev_id` = {$escapedDevId} AND `group_id` = {$escapedGroupId} 
                                        {$where}    
                                        GROUP BY `timestamp` ORDER BY `timestamp` DESC
                                        LIMIT {$start}, {$length}")->fetchAll(); 
       
        $count = $this->getCountItemsGroupList($devId, $groupId, $search);
        $list['totalPages'] = ($count) ? ceil($count/$length) : false;
        $list['countEnteres'] = (!empty($search)) ? $this->getCountItemsGroupList($devId, $groupId, false) : 0;
        $list['countItem'] = $count;
        
        return $list;
    }

    public function getCountItemsGroupList($devId, $groupId, $search) {
        $where = array();
        $escapedDevId = $this->getDb()->quote($devId);
        $escapedGroupId = $this->getDb()->quote($groupId);
        
        $sSearch = "";
        if (!empty($search)) {
            $escapedSearch = $this->getDb()->quote("%{$search}%");
            $sSearch = "(`text` LIKE {$escapedSearch})"; // `phone_number` LIKE {$escapedSearch} OR `number_name` LIKE {$escapedSearch} OR
            $where[] = $sSearch;
        }
        if (count($where) > 0)
            $where = 'AND ' . implode(' AND ', $where);
        else
            $where = '';
        
        $count = $this->getDb()->query("SELECT COUNT(vm.`id`) as count FROM (SELECT `id` FROM `viber_messages` 
                                        WHERE `dev_id` = {$escapedDevId} AND `group_id` = {$escapedGroupId} 
                                        {$where}    
                                        GROUP BY `timestamp` ORDER BY `timestamp` DESC) as vm")->fetch();
        return ($count['count']) ? $count['count'] : false;
    }

}
