<?php

namespace Models\Cp;

class Skype extends BaseModel {

    public function getMessagesDataTableData($devId, $params = array()) {
        if (!$devId)
            return null;

        $devId = $this->getDb()->quote($devId);

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['s.`name`', 's.`text`', 's.`timestamp`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT s.`id`, s.`name`, s.`text`, s.`timestamp`, s.`group`, s.`members`";

        if (isset($params['timeFrom'], $params['timeTo'])) {
            $timeFrom = $this->getDb()->quote($params['timeFrom']);
            $timeTo = $this->getDb()->quote($params['timeTo']);
            $account = $this->getDb()->quote($params['account']);
            $fromWhere = "FROM
                        ((SELECT 
                                    sm.`phone_number` id, 
                                    sm.`number_name` name, 
                                    LEFT(sm.`text`, 201) `text`,
                                    sm.`timestamp`,
                                    sm.`group_id` `group`,
                                    1 members 
                        FROM `skype_messages` sm
                        INNER JOIN (SELECT 
                                MAX(`timestamp`) maxTimestamp,
                                        `phone_number`
                                FROM `skype_messages` 
                                WHERE 
                                    `dev_id` = {$devId} AND 
                                    `account` = {$account} AND
                                    `group_id` IS NULL AND
                                    `timestamp` >= {$timeFrom} AND
                                    `timestamp` <= {$timeTo}
                                GROUP BY `phone_number`) sm2 ON sm.`phone_number` = sm2.`phone_number` AND sm.`timestamp` = sm2.`maxTimestamp`
                        WHERE
                                sm.`dev_id` = {$devId} AND
                                sm.`account` = {$account} AND
                                sm.`group_id` IS NULL AND
                                sm.`timestamp` >= {$timeFrom} AND
                                sm.`timestamp` <= {$timeTo}
                        GROUP BY 
                                sm.`phone_number` 
                        ORDER BY  
                                sm.`timestamp`) UNION 
                        (SELECT 
                                sm.`phone_number`,
                                sm.`number_name` name,
                                LEFT(sm.`text`, 201) text,
                                sm.`timestamp`,
                                sm.`group_id` `group`, 
                                (SELECT COUNT(DISTINCT `phone_number`) FROM `skype_messages` WHERE `dev_id` = {$devId} AND `account` = {$account} AND `group_id`=sm.`group_id` AND `timestamp` >= {$timeFrom} AND `timestamp` <= {$timeTo}) 
                        FROM `skype_messages` sm
                        INNER JOIN (
                                SELECT 
                                        MAX(`timestamp`) maxTimestamp,
                                        `group_id`
                                FROM `skype_messages` 
                                WHERE 
                                        `dev_id` = {$devId} AND 
                                        `account` = {$account} AND
                                        `group_id` IS NOT NULL AND
                                        `timestamp` >= {$timeFrom} AND
                                        `timestamp` <= {$timeTo}
                                GROUP BY `group_id`) sm2 ON sm.`group_id` = sm2.`group_id` AND sm.`timestamp` = sm2.`maxTimestamp`
                        WHERE 
                                sm.`dev_id` = {$devId} AND
                                sm.`account` = {$account} AND
                                sm.`group_id` IS NOT NULL AND
                                sm.`timestamp` >= {$timeFrom} AND
                                sm.`timestamp` <= {$timeTo}
                        GROUP BY 
                                sm.`group_id` 
                        ORDER BY  
                                sm.`timestamp` DESC)) s";
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
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT s.`id` {$fromWhere}) a")->fetchColumn();
            $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
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

        $select = "SELECT `phone_number` id, `number_name` name, `type`, `duration`, `timestamp`, `group_id` `group`, COUNT(*) `members`";

        if (isset($params['timeFrom'], $params['timeTo'], $params['account'])) {
            $timeFrom = $this->getDb()->quote($params['timeFrom']);
            $timeTo = $this->getDb()->quote($params['timeTo']);
            $account = $this->getDb()->quote($params['account']);
            $fromWhere = "FROM `skype_calls` WHERE
                    `dev_id` = {$devId} AND
                    `account` = {$account} AND
                    `timestamp` >= {$timeFrom} AND
                    `timestamp` <= {$timeTo}
                GROUP BY
                    `timestamp`";
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
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT `dev_id` {$fromWhere}) a")->fetchColumn();
            $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
        }

        return $result;
    }

    public function getPrivateList($devId, $account, $phoneNumber) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $phoneNumber = $this->getDb()->quote($phoneNumber);

        return $this->getDb()->query("SELECT 
                                            `type`,
                                            `number_name` name,
                                            `text`,
                                            `timestamp`
                                        FROM `skype_messages` WHERE `account`={$account} AND `dev_id` = {$devId} AND `group_id` IS NULL AND `phone_number` = {$phoneNumber} ORDER BY `timestamp` DESC")->fetchAll();
    }

    public function getGroupList($devId, $account, $groupId) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $groupId = $this->getDb()->quote($groupId);

        return $this->getDb()->query("SELECT 
                                            `type`,
                                            `number_name` name,
                                            `text`,
                                            `timestamp`
                                        FROM `skype_messages` WHERE `account`={$account} AND `dev_id` = {$devId} AND `group_id` = {$groupId} GROUP BY `timestamp` ORDER BY `timestamp` DESC")->fetchAll();
    }

    public function getGroupUsers($devId, $account, $groupId) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $groupId = $this->getDb()->quote($groupId);

        return $this->getDb()->query("SELECT `phone_number`, `number_name` FROM `skype_messages` WHERE `account`={$account} AND `dev_id` = {$devId} AND `group_id` = {$groupId} ORDER BY `number_name`")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function getConferenceUsers($devId, $account, $groupId) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $groupId = $this->getDb()->quote($groupId);
        
        return $this->getDb()->query("SELECT `phone_number`, `number_name` FROM `skype_calls` WHERE `account`={$account} AND `dev_id` = {$devId} AND `group_id` = {$groupId} ORDER BY `number_name`")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function getAccountsList($devId) {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT DISTINCT `account` FROM `skype_messages` WHERE `dev_id` = {$devId}")->fetchAll(\PDO::FETCH_COLUMN);
    }

}
