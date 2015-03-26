<?php

namespace Models\Cp;

class Vk extends BaseModel {

    public function getPrivateDataTableData($devId, $params = array()) {
        $devId = $this->getDb()->quote($devId);

        if (count($params['sortColumns'])) {
            $columns = ['vk.`user_name`', 'vk.`dev_id`', 'vk.`timestamp`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        } else {
            $sort = '`timestamp` ASC';
        }

        $select = "SELECT vk.`timestamp`, vk.`user_name`, vk.`user_id`, LEFT(vk.`text`, 201) `text`";

        $timeFrom = $this->getDb()->quote($params['timeFrom']);
        $timeTo = $this->getDb()->quote($params['timeTo']);
        $account = $this->getDb()->quote($params['account']);

        $fromWhere = "FROM `vk_messages` vk
                        INNER JOIN (
                            SELECT 
                                MAX(`timestamp`) maxTimestamp,
                                `user_id`
                            FROM `vk_messages` 
                            WHERE 
                                `dev_id` = {$devId} AND
                                `is_group` = 0 AND
                                `timestamp` >= {$timeFrom} AND
                                `timestamp` <= {$timeTo}
                            GROUP BY `user_id`
                        ) vk2 ON vk.`user_id` = vk2.`user_id` AND vk.`timestamp` = vk2.`maxTimestamp`
                        WHERE
                              vk.`dev_id` = {$devId} AND
                              vk.`is_group` = 0 AND
                              vk.`account_id` = {$account} AND
                              vk.`timestamp` >= {$timeFrom} AND
                              vk.`timestamp` <= {$timeTo}
                        GROUP BY vk.`user_id`";

        $query = "{$select} {$fromWhere}"
                . " ORDER BY {$sort} LIMIT {$params['start']}, {$params['length']}";

        $result = array(
            'aaData' => $this->getDb()->query($query)->fetchAll(\PDO::FETCH_ASSOC)
        );

        if (empty($result['aaData'])) {
            $result['iTotalRecords'] = 0;
            $result['iTotalDisplayRecords'] = 0;
        } else {
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT vk.`timestamp` {$fromWhere}) a")->fetchColumn();
            $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
        }

        return $result;
    }

    public function getGroupDataTableData($devId, $params = array()) {
        $devId = $this->getDb()->quote($devId);

        if (count($params['sortColumns'])) {
            $columns = ['vk.`group_name`', 'vk.`dev_id`', 'vk.`timestamp`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        } else {
            $sort = '`timestamp` ASC';
        }

        $select = "SELECT vk.`timestamp`, vk.`group_title`, vk.`group_id`, LEFT(vk.`text`, 201) `text`";

        $timeFrom = $this->getDb()->quote($params['timeFrom']);
        $timeTo = $this->getDb()->quote($params['timeTo']);
        $account = $this->getDb()->quote($params['account']);

        $fromWhere = "FROM `vk_messages` vk
                        INNER JOIN (
                            SELECT 
                                MAX(`timestamp`) maxTimestamp,
                                `group_id`
                            FROM `vk_messages` 
                            WHERE 
                                `dev_id` = {$devId} AND
                                `is_group` = 1 AND
                                `timestamp` >= {$timeFrom} AND
                                `timestamp` <= {$timeTo}
                            GROUP BY `user_id`
                        ) vk2 ON vk.`group_id` = vk2.`group_id` AND vk.`timestamp` = vk2.`maxTimestamp`
                        WHERE
                              vk.`dev_id` = {$devId} AND
                              vk.`is_group` = 1 AND
                              vk.`account_id` = {$account} AND
                              vk.`timestamp` >= {$timeFrom} AND
                              vk.`timestamp` <= {$timeTo}
                        GROUP BY vk.`group_id`";

        $query = "{$select} {$fromWhere}"
                . " ORDER BY {$sort} LIMIT {$params['start']}, {$params['length']}";

        $result = array(
            'aaData' => $this->getDb()->query($query)->fetchAll(\PDO::FETCH_ASSOC)
        );

        if (empty($result['aaData'])) {
            $result['iTotalRecords'] = 0;
            $result['iTotalDisplayRecords'] = 0;
        } else {
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT vk.`timestamp` {$fromWhere}) a")->fetchColumn();
            $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
        }

        return $result;
    }

    public function getAccountsList($devId) {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT 
                    vk.`account_id`, 
                    vk.`account_name`
                FROM `vk_messages` vk
                INNER JOIN (
                    SELECT
                        `account_id`,
                        max(`timestamp`) maxTimestamp
                    FROM `vk_messages`
                    WHERE `dev_id` = {$devId}
                    GROUP BY `account_id`
                ) vk2 ON vk2.`account_id`=vk.`account_id` AND vk2.`maxTimestamp`=vk.`timestamp`
                WHERE vk.`dev_id` = {$devId}
                GROUP BY vk.`account_id`")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function getPrivateList($devId, $account, $userId) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $userId = $this->getDb()->quote($userId);

        return $this->getDb()->query("SELECT 
                                            `type`,
                                            `user_name` name,
                                            `user_id` id,
                                            `text`,
                                            `timestamp`
                                        FROM `vk_messages` WHERE `dev_id` = {$devId} AND `account_id` = {$account} AND `is_group` = 0 AND `user_id` = {$userId} ORDER BY `timestamp` DESC")->fetchAll();
    }

    public function getGroupList($devId, $account, $groupId) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $groupId = $this->getDb()->quote($groupId);

        return $this->getDb()->query("SELECT 
                                            `type`,
                                            `user_name` name,
                                            `user_id` id,
                                            `text`,
                                            `timestamp`
                                        FROM `vk_messages` WHERE `dev_id` = {$devId} AND `account_id` = {$account} AND `is_group` = 1 AND `group_id` = {$groupId} ORDER BY `timestamp` DESC")->fetchAll();
    }
    
    public function getGroupUsers($devId, $account, $groupId) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $groupId = $this->getDb()->quote($groupId);
        
        return $this->getDb()->query("SELECT `user_id`, `user_name` FROM `vk_messages` WHERE `dev_id`={$devId} AND `account_id`={$account} AND `is_group` = 1 AND `group_id`={$groupId} AND `user_id` > 0 GROUP BY `user_id` ORDER BY `user_name`")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

}
