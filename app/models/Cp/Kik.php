<?php

namespace Models\Cp;

class Kik extends BaseModel {

    public function getDataTableData($devId, $params = array()) {
        $devId = $this->getDb()->quote($devId);

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['`name`', 'k.`text`', 'k.`timestamp`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $timeFrom = $this->getDb()->quote($params['timeFrom']);
        $timeTo = $this->getDb()->quote($params['timeTo']);
        $account = $this->getDb()->quote($params['account']);
        
        $select = "SELECT k.`id`, k.`text`, k.`timestamp`, k.`is_group`, k.`group_id`, IF(k.`is_group`, 'group', (SELECT `nickname` FROM `kik_users` WHERE `dev_id` = {$devId} AND `account_id` = {$account} AND `user_id` = k.`sender_id` LIMIT 1)) name";

        $fromWhere = "FROM (SELECT
                            `group_id`,
                            MAX(`timestamp`) as max_time
                        FROM kik_messages
                        WHERE
                            `dev_id` = {$devId} AND
                            `account_id` = {$account} AND
                            `timestamp` >= {$timeFrom} AND
                            `timestamp` <= {$timeTo}
                        GROUP BY `group_id`) last
                        INNER JOIN `kik_messages` k ON k.`dev_id` = {$devId} AND k.`account_id` = {$account} AND k.`group_id` = last.`group_id` AND last.`max_time` = k.`timestamp`";

        $query = "{$select} {$fromWhere}"
                . " ORDER BY {$sort} LIMIT {$params['start']}, {$params['length']}";
                
        $result = array(
            'aaData' => $this->getDb()->query($query)->fetchAll(\PDO::FETCH_ASSOC)
        );

        if (empty($result['aaData'])) {
            $result['iTotalRecords'] = 0;
            $result['iTotalDisplayRecords'] = 0;
        } else {
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT k.`id` {$fromWhere}) a")->fetchColumn();
            $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
        }

        return $result;
    }

    public function getAccountsList($devId) {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT `account_id`, `nickname` FROM `kik_users` WHERE `dev_id` = {$devId} AND `account_id` = `user_id`")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function getMessagesList($devId, $account, $group) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $group = $this->getDb()->quote($group);

        return $this->getDb()->query("SELECT
                                            km.`sender_id` id,
                                            IF(km.`sender_id`=km.`account_id`, 'out', 'in') type,
                                            ku.`nickname` name,
                                            km.`text`,
                                            km.`timestamp`
                                        FROM `kik_messages` km
                                        INNER JOIN `kik_users` ku ON ku.`dev_id` = {$devId} AND ku.`account_id` = {$account} AND ku.`user_id` = km.`sender_id`
                                        WHERE 
                                            km.`dev_id` = {$devId} AND
                                            km.`account_id` = {$account} AND
                                            km.`group_id` = {$group}
                                        ORDER BY `timestamp` DESC")->fetchAll();
    }

    public function getUserName($devId, $account, $userId) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $userId = $this->getDb()->quote($userId);

        return $this->getDb()->query("SELECT `nickname` FROM `kik_users` WHERE `dev_id` = {$devId} AND `account_id` = {$account} AND `user_id` = {$userId} LIMIT 1")->fetchColumn();
    }

    public function getGroupUsers($devId, $account, $groupId) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $groupId = $this->getDb()->quote($groupId);

        return $this->getDb()->query("SELECT 
                kgm.`user_id`,
                ku.`nickname`
            FROM `kik_group_members` kgm
            INNER JOIN `kik_users` ku ON ku.`dev_id` = {$devId} AND ku.`account_id` = {$account} AND ku.`user_id` = kgm.`user_id`
            WHERE 
                kgm.`dev_id` = {$devId} AND
                kgm.`account_id` = {$account} AND
                kgm.`group_id` = {$groupId}")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    
    /**
     * Paginate
     */
    public function getItemsPrivateList($devId, $account, $groupid, $search, $page = 0, $length = 10) {
        $where = array();
        $escapedDevId = $this->getDb()->quote($devId);
        $escapedGroupId = $this->getDb()->quote($groupid);
        $escapedAccount = $this->getDb()->quote($account);
        
        $start = ($page <= 0 ) ?  0 : $page - 1;  
        $start *= $length;
        
        $sSearch = "";
        if (!empty($search)) {
            $escapedSearch = $this->getDb()->quote("%{$search}%");
            $sSearch = "(ku.`nickname` LIKE {$escapedSearch} OR km.`text` LIKE {$escapedSearch})";
            $where[] = $sSearch;
        }
        if (count($where) > 0)
            $where = 'AND ' . implode(' AND ', $where);
        else
            $where = '';
        
        $list['items'] = $this->getDb()->query("SELECT
                                            km.`sender_id` id,
                                            IF(km.`sender_id`=km.`account_id`, 'out', 'in') type,
                                            ku.`nickname` name,
                                            km.`text`,
                                            km.`timestamp`
                                        FROM `kik_messages` km
                                        INNER JOIN `kik_users` ku ON ku.`dev_id` = {$escapedDevId} AND ku.`account_id` = {$escapedAccount} AND ku.`user_id` = km.`sender_id`
                                        WHERE 
                                            km.`dev_id` = {$escapedDevId} AND
                                            km.`account_id` = {$escapedAccount} AND
                                            km.`group_id` = {$escapedGroupId}
                                            {$where}    
                                        ORDER BY `timestamp` DESC LIMIT {$start}, {$length}")->fetchAll(); 
       
        $count = $this->getCountItemsPrivateList($devId, $account, $groupid, $search);
        $list['totalPages'] = ($count) ? ceil($count/$length) : false;
        $list['countEnteres'] = (!empty($search)) ? $this->getCountItemsPrivateList($devId, $account, $groupid, false) : 0;
        $list['countItem'] = $count;
        
        return $list;
    }

    public function getCountItemsPrivateList($devId, $account, $groupid, $search) {
        $where = array();
        $escapedDevId = $this->getDb()->quote($devId);
        $escapedGroupId = $this->getDb()->quote($groupid);
        $escapedAccount = $this->getDb()->quote($account);
        
        $sSearch = "";
        if (!empty($search)) {
            $escapedSearch = $this->getDb()->quote("%{$search}%");
            $sSearch = "(ku.`nickname` LIKE {$escapedSearch} OR km.`text` LIKE {$escapedSearch})";
            $where[] = $sSearch;
        }
        if (count($where) > 0)
            $where = 'AND ' . implode(' AND ', $where);
        else
            $where = '';
        
        $count = $this->getDb()->query("SELECT
                                            COUNT(km.`sender_id`) AS count
                                        FROM `kik_messages` km
                                        INNER JOIN `kik_users` ku ON ku.`dev_id` = {$escapedDevId} AND ku.`account_id` = {$escapedAccount} AND ku.`user_id` = km.`sender_id`
                                        WHERE 
                                            km.`dev_id` = {$escapedDevId} AND
                                            km.`account_id` = {$escapedAccount} AND
                                            km.`group_id` = {$escapedGroupId}
                                            {$where}    
                                        ORDER BY `timestamp` DESC")->fetch();
        return ($count['count']) ? $count['count'] : false;
    }
    
    public function getItemsGroupList($devId, $account, $groupId, $search, $page = 0, $length = 10) {
        $where = array();
        $escapedDevId = $this->getDb()->quote($devId);
        $escapedGroupId = $this->getDb()->quote($groupId);
        $escapedAccount = $this->getDb()->quote($account);
        
        $start = ($page <= 0 ) ?  0 : $page - 1;  
        $start *= $length;
        
        $sSearch = "";
        if (!empty($search)) {
            $escapedSearch = $this->getDb()->quote("%{$search}%");
            $sSearch = "(ku.`nickname` LIKE {$escapedSearch})"; // `phone_number` LIKE {$escapedSearch} OR `number_name` LIKE {$escapedSearch} OR
            $where[] = $sSearch;
        }
        if (count($where) > 0)
            $where = 'AND ' . implode(' AND ', $where);
        else
            $where = '';
        
        $list['items'] = $this->getDb()->query("SELECT 
                                                    kgm.`user_id`,
                                                    ku.`nickname`
                                                FROM `kik_group_members` kgm
                                                INNER JOIN `kik_users` ku ON ku.`dev_id` = {$escapedDevId} AND ku.`account_id` = {$escapedAccount} AND ku.`user_id` = kgm.`user_id`
                                                WHERE 
                                                    kgm.`dev_id` = {$escapedDevId} AND
                                                    kgm.`account_id` = {$escapedAccount} AND
                                                    kgm.`group_id` = {$escapedGroupId}
                                                    {$where}
                                                ORDER BY `timestamp` DESC        
                                                LIMIT {$start}, {$length}")->fetchAll(); 
       
        $count = $this->getCountItemsGroupList($devId, $account, $groupId, $search);
        $list['totalPages'] = ($count) ? ceil($count/$length) : false;
        $list['countEnteres'] = (!empty($search)) ? $this->getCountItemsGroupList($devId, $account, $groupId, false) : 0;
        $list['countItem'] = $count;
        
        return $list;
    }

    public function getCountItemsGroupList($devId, $account, $groupId, $search) {
        $where = array();
        $escapedDevId = $this->getDb()->quote($devId);
        $escapedGroupId = $this->getDb()->quote($groupId);
        $escapedAccount = $this->getDb()->quote($account);
        
        $sSearch = "";
        if (!empty($search)) {
            $escapedSearch = $this->getDb()->quote("%{$search}%");
            $sSearch = "(ku.`nickname` LIKE {$escapedSearch})"; // `phone_number` LIKE {$escapedSearch} OR `number_name` LIKE {$escapedSearch} OR
            $where[] = $sSearch;
        }
        if (count($where) > 0)
            $where = 'AND ' . implode(' AND ', $where);
        else
            $where = '';
        
        $count = $this->getDb()->query("SELECT COUNT(_kgm.`id`) as count FROM (SELECT 
                                                    kgm.`user_id`,
                                                    ku.`nickname`
                                                FROM `kik_group_members` kgm
                                                INNER JOIN `kik_users` ku ON ku.`dev_id` = {$escapedDevId} AND ku.`account_id` = {$escapedAccount} AND ku.`user_id` = kgm.`user_id`
                                                WHERE 
                                                    kgm.`dev_id` = {$escapedDevId} AND
                                                    kgm.`account_id` = {$escapedAccount} AND
                                                    kgm.`group_id` = {$escapedGroupId}
                                                    {$where}
                                                ORDER BY `timestamp` DESC) as _kgm")->fetch();
        return ($count['count']) ? $count['count'] : false;
    }
}
