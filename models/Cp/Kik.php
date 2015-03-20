<?php

namespace Models\Cp;

class Kik extends BaseModel {

    public function getDataTableData($devId, $params = array()) {
        if (!$devId)
            return null;

        $devId = $this->getDb()->quote($devId);

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['f.`user_name`', 'f.`text`', 'f.`timestamp`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT f.`id`, f.`name`, f.`text`, f.`timestamp`, f.`group`, f.`members`";

        if (isset($params['timeFrom'], $params['timeTo'], $params['account'])) {
            $timeFrom = $this->getDb()->quote($params['timeFrom']);
            $timeTo = $this->getDb()->quote($params['timeTo']);
            $account = $this->getDb()->quote($params['account']);
            $fromWhere = "FROM
                        ((SELECT 
                                    fm.`user_id` id, 
                                    fm.`user_name` name, 
                                    LEFT(fm.`text`, 201) `text`,
                                    fm.`timestamp`,
                                    fm.`group_id` `group`,
                                    1 members 
                        FROM `facebook_messages` fm
                        INNER JOIN (SELECT 
                                MAX(`timestamp`) maxTimestamp,
                                        `user_id`
                                FROM `facebook_messages` 
                                WHERE 
                                    `dev_id` = {$devId} AND 
                                    `account` = {$account} AND
                                    `group_id` IS NULL AND
                                    `timestamp` >= {$timeFrom} AND
                                    `timestamp` <= {$timeTo}
                                GROUP BY `user_name`) fm2 ON fm.`user_id` = fm2.`user_id` AND fm.`timestamp` = fm2.`maxTimestamp`
                        WHERE
                                fm.`dev_id` = {$devId} AND
                                fm.`account` = {$account} AND
                                fm.`group_id` IS NULL AND
                                fm.`timestamp` >= {$timeFrom} AND
                                fm.`timestamp` <= {$timeTo}
                        GROUP BY 
                                fm.`user_name` 
                        ORDER BY  
                                fm.`timestamp`) UNION 
                        (SELECT 
                                fm.`user_id`,
                                fm.`user_name` name,
                                LEFT(fm.`text`, 201) text,
                                fm.`timestamp`,
                                fm.`group_id` `group`, 
                                (SELECT COUNT(DISTINCT `user_id`) FROM `facebook_messages` WHERE `dev_id` = {$devId} AND `account` = {$account} AND `group_id`=fm.`group_id` AND `timestamp` >= {$timeFrom} AND `timestamp` <= {$timeTo}) 
                        FROM `facebook_messages` fm
                        INNER JOIN (
                                SELECT 
                                        MAX(`timestamp`) maxTimestamp,
                                        `group_id`
                                FROM `facebook_messages` 
                                WHERE 
                                        `dev_id` = {$devId} AND 
                                        `account` = {$account} AND
                                        `group_id` IS NOT NULL AND
                                        `timestamp` >= {$timeFrom} AND
                                        `timestamp` <= {$timeTo}
                                GROUP BY `group_id`) fm2 ON fm.`group_id` = fm2.`group_id` AND fm.`timestamp` = fm2.`maxTimestamp`
                        WHERE 
                                fm.`dev_id` = {$devId} AND
                                fm.`account` = {$account} AND
                                fm.`group_id` IS NOT NULL AND
                                fm.`timestamp` >= {$timeFrom} AND
                                fm.`timestamp` <= {$timeTo}
                        GROUP BY 
                                fm.`group_id` 
                        ORDER BY  
                                fm.`timestamp` DESC)) f";
        }

        $query = "{$select} {$fromWhere}"
                . " ORDER BY {$sort} LIMIT {$params['start']}, {$params['length']}";

        $result = array(
            'aaData' => $this->getDb()->query($query)->fetchAll(\PDO::FETCH_ASSOC)
        );

        if (empty($result['aaData'])) {
            $result['iTotalRecords'] = 0;
            $result['iTotalDisplayRecords'] = 0;
        } else {
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT f.`id` {$fromWhere}) a")->fetchColumn();
            $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
        }

        return $result;
    }

    public function getAccountsList($devId) {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT DISTINCT `account_id` FROM `kik_messages` WHERE `dev_id` = {$devId}")->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getPrivateList($devId, $account, $userId) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $userId = $this->getDb()->quote($userId);

        return $this->getDb()->query("SELECT
                                            `user_id` id,
                                            `type`,
                                            `user_name` name,
                                            `text`,
                                            `timestamp`
                                        FROM `facebook_messages` WHERE `dev_id` = {$devId} AND `group_id` IS NULL AND `account` = {$account} AND `user_id` = {$userId} ORDER BY `timestamp` DESC")->fetchAll();
    }

    public function getGroupList($devId, $account, $groupId) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $groupId = $this->getDb()->quote($groupId);

        return $this->getDb()->query("SELECT
                                            `user_id` id,
                                            `type` type,
                                            `user_name` name,
                                            `text`,
                                            `timestamp`
                                        FROM `facebook_messages` WHERE `dev_id` = {$devId} AND `account` = {$account} AND `group_id` = {$groupId} GROUP BY `timestamp` ORDER BY `timestamp` DESC")->fetchAll();
    }

    public function getGroupUsers($devId, $account, $groupId) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $groupId = $this->getDb()->quote($groupId);

        return $this->getDb()->query("SELECT DISTINCT `user_id`, `user_name` FROM `facebook_messages` WHERE `dev_id` = {$devId} AND `account` = {$account} AND `group_id` = {$groupId} ORDER BY `user_name`")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

}
