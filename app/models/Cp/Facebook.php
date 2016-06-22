<?php

namespace Models\Cp;

class Facebook extends BaseModel {

    private static $_authLifeTime = 3600;
    
    public function getCDNAuthorizedUrl($uri)
    {
        $s3 = $this->di->get('S3');
        return $s3->getSignedCannedURL($this->di['config']['cloudFront']['domain'] . $uri, self::$_authLifeTime);
    }
    
    public function getMessagesDataTableData($devId, $params = array()) {
        if (!$devId)
            return null;

        $devId = $this->getDb()->quote($devId);

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['f.`name`', 'f.`text`', 'f.`timestamp`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT f.`id`, f.`name`, f.`text`, f.`timestamp`, f.`group`, f.`members`, f.`sticker`, f.location, (SELECT `mime_type` FROM `facebook_attachment` WHERE message_id = f.message_id LIMIT 1) attachment";

        
        $timeFrom = $this->getDb()->quote($params['timeFrom']);
        $timeTo = $this->getDb()->quote($params['timeTo']);
        $account = $this->getDb()->quote($params['account']);
        
        if ($params['timeFrom'] > 0 && $params['timeTo'] > 0) {
            $timeQuery = "`timestamp` >= {$timeFrom} AND `timestamp` <= {$timeTo}";
        } else {
            $timeQuery = "1";
        }
        
        $fromWhere = "FROM
                    ((SELECT
                                fm.`id` message_id,
                                fm.`user_id` id, 
                                fm.`user_name` name, 
                                LEFT(fm.`text`, 201) `text`,
                                fm.`sticker`,
                                fm.`latitude` location,
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
                                {$timeQuery}
                            GROUP BY `user_name`) fm2 ON fm.`user_id` = fm2.`user_id` AND fm.`timestamp` = fm2.`maxTimestamp`
                    WHERE
                            fm.`dev_id` = {$devId} AND
                            fm.`account` = {$account} AND
                            fm.`group_id` IS NULL AND
                            {$timeQuery}
                    GROUP BY 
                            fm.`user_name` 
                    ORDER BY  
                            fm.`timestamp`) UNION 
                    (SELECT 
                            fm.`id` message_id,
                            fm.`user_id` id,
                            fm.`user_name` name,
                            LEFT(fm.`text`, 201) text,
                            fm.`sticker`,
                            fm.`latitude` location,
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
                                    {$timeQuery}
                            GROUP BY `group_id`) fm2 ON fm.`group_id` = fm2.`group_id` AND fm.`timestamp` = fm2.`maxTimestamp`
                    WHERE 
                            fm.`dev_id` = {$devId} AND
                            fm.`account` = {$account} AND
                            fm.`group_id` IS NOT NULL AND
                            {$timeQuery}
                    GROUP BY 
                            fm.`group_id` 
                    ORDER BY  
                            fm.`timestamp` DESC)) f";

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
    
    public function getCallsDataTableData($devId, $params = array(), $os) {
        $devId = $this->getDb()->quote($devId);

        $search = '';
        if (!empty($params['search'])) {
            $searched = $this->getDb()->quote('%' . $params['search'] . '%');
            $search = "`user_name` LIKE {$searched} OR `user_id` LIKE {$searched}";
        }

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            if ($os === 'android') {
                $columns = ['`user_name`', '`type`', '`call_type`', '`timestamp`'];
            } elseif ($os === 'ios') {
                $columns = ['`user_name`', '`type`', '`duration`', '`timestamp`'];
            }

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        if ($os === 'android') {
            $select = "SELECT `user_id` id, `user_name` name, `type`, `call_type`, `timestamp`";
        } elseif ($os === 'ios') {
            $select = "SELECT `user_id` id, `user_name` name, `type`, `duration`, `timestamp`";
        }

        $timeFrom = $this->getDb()->quote($params['timeFrom']);
        $timeTo = $this->getDb()->quote($params['timeTo']);
        $account = $this->getDb()->quote($params['account']);
        
        if ($params['timeFrom'] > 0 && $params['timeTo'] > 0) {
            $timeQuery = "`timestamp` >= {$timeFrom} AND `timestamp` <= {$timeTo}";
        } else {
            $timeQuery = "1";
        }
        
        $fromWhere = "FROM `facebook_calls` WHERE `dev_id` = {$devId} AND `account` = {$account} AND {$timeQuery}";


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

    public function getAccountsList($devId) {
        $devId = $this->getDb()->quote($devId);

        $accounts =  $this->getDb()->query("SELECT DISTINCT `account` FROM `facebook_messages` WHERE `dev_id` = {$devId}")->fetchAll(\PDO::FETCH_COLUMN);
        $callsAccounts =  $this->getDb()->query("SELECT DISTINCT `account` FROM `facebook_calls` WHERE `dev_id` = {$devId}")->fetchAll(\PDO::FETCH_COLUMN);
        
        return array_unique(array_merge($accounts, $callsAccounts));
    }

    public function getAccountName($devId, $account, $userId) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $userId = $this->getDb()->quote($userId);

        return $this->getDb()->query("SELECT `user_name` FROM `facebook_messages` WHERE `dev_id` = {$devId} AND `group_id` IS NULL AND `account` = {$account} AND `user_id` = {$userId} ORDER BY `timestamp` DESC LIMIT 1")->fetchColumn();
    }

    public function isGroupDialogueExists($devId, $account, $groupId) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $groupId = $this->getDb()->quote($groupId);

        return $this->getDb()->query("SELECT `id` FROM `facebook_messages` WHERE `dev_id` = {$devId} AND `account` = {$account} AND `group_id` = {$groupId} LIMIT 1")->fetchColumn() !== false;
    }

    public function getGroupUsers($devId, $account, $groupId) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $groupId = $this->getDb()->quote($groupId);

        return $this->getDb()->query("SELECT DISTINCT `user_id`, `user_name` FROM `facebook_messages` WHERE `dev_id` = {$devId} AND `account` = {$account} AND `group_id` = {$groupId} ORDER BY `user_name`")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
    
    /**
     * Paginate
     */
    public function getItemsPrivateList($devId, $account, $userId, $search, $page = 0, $length = 10) {
        $where = array();
        $escapedDevId = $this->getDb()->quote($devId);
        $escapedUserId = $this->getDb()->quote($userId);
        $escapedAccount = $this->getDb()->quote($account);
        
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
        
        $messages = $this->getDb()->query("
                                        SELECT
                                            fm.*,
                                            fa.`attachment_id`,
                                            fa.`mime_type`, 
                                            fa.`filename`, 
                                            fa.`media`, 
                                            fa.`thumbnail`, 
                                            fa.`status`
                                        FROM (
                                            SELECT
                                                `id` message_id,
                                                `user_id` id,
                                                `type`,
                                                `user_name` name,
                                                `text`,
                                                `timestamp`,
                                                `sticker`,
                                                `latitude`,
                                                `longitude`
                                            FROM `facebook_messages`
                                            WHERE 
                                                `dev_id` = {$escapedDevId} AND `group_id` IS NULL AND `account` = {$escapedAccount} AND `user_id` = {$escapedUserId}
                                                {$where}    
                                            ORDER BY `timestamp` DESC
                                            LIMIT {$start}, {$length}
                                        ) fm
                                        LEFT JOIN `facebook_attachment` fa ON fa.`message_id` = fm.`message_id`")->fetchAll();
          
        $list['items'] = $this->wrapMessagesData($messages);
                                        
        $count = $this->getCountItemsPrivateList($devId, $account, $userId, $search);
        $list['totalPages'] = ($count) ? ceil($count/$length) : false;
        $list['countEnteres'] = (!empty($search)) ? $this->getCountItemsPrivateList($devId, $account, $userId, false) : 0;
        $list['countItem'] = $count;
        
        return $list;
    }

    public function getCountItemsPrivateList($devId, $account, $userId, $search) {
        $where = array();
        $escapedDevId = $this->getDb()->quote($devId);
        $escapedUserId = $this->getDb()->quote($userId);
        $escapedAccount = $this->getDb()->quote($account);
        
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
        
        $count = $this->getDb()->query("SELECT COUNT(`id`) as count FROM `facebook_messages` WHERE `dev_id` = {$escapedDevId} AND `group_id` IS NULL AND `account` = {$escapedAccount} AND `user_id` = {$escapedUserId} {$where} ORDER BY `timestamp` DESC")->fetch();
        return ($count['count']) ? $count['count'] : false;
    }
    
    private function wrapMessagesData($data) {
        $result = [];
        
        $last = null;
        foreach ($data as $item) {
            if ($item['attachment_id'] > 0) {
                $attachment = [
                    'mime_type' => $item['mime_type'],
                    'status' => $item['status'],
                    'filename' => $item['filename']
                ];
                
                $isImage = strpos($item['mime_type'], 'image/') === 0;
                if ($attachment['status'] == 'uploaded' && $isImage && strlen($item['media'])) {
                    $attachment['media'] = $this->getCDNAuthorizedUrl($item['media']);
                    $attachment['thumbnail'] = $this->getCDNAuthorizedUrl($item['thumbnail']);
                }
            } else {
                $attachment = null;
            }
            
            if ($last != $item['message_id']) {
                $facebookMessage = [
                    'id' => $item['id'],
                    'type' => $item['type'],
                    'name' => $item['name'],
                    'text' => $item['text'],
                    'timestamp' => $item['timestamp'],
                    'attachments' => []
                ];
                
                if ($item['sticker'] > 0) {
                    $facebookMessage['sticker'] = $item['sticker'];
                } elseif (is_numeric($item['latitude']) && is_numeric($item['longitude'])) {
                    $facebookMessage['location'] = [
                        'latitude' => $item['latitude'],
                        'longitude' => $item['longitude']
                    ];
                }
                
                if ($attachment) {
                    array_push($facebookMessage['attachments'], $attachment);                    
                }
                
                array_push($result, $facebookMessage);
                $last = $item['message_id'];
            } else {
                array_push($result[count($result) - 1]['attachments'], $attachment);
            }
        }
        
        return $result;
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
            $sSearch = "(`text` LIKE {$escapedSearch})"; // `phone_number` LIKE {$escapedSearch} OR `number_name` LIKE {$escapedSearch} OR
            $where[] = $sSearch;
        }
        if (count($where) > 0)
            $where = 'AND ' . implode(' AND ', $where);
        else
            $where = '';
        
        $messages = $this->getDb()->query("
                                        SELECT
                                            fm.*,
                                            fa.`attachment_id`,
                                            fa.`mime_type`, 
                                            fa.`filename`, 
                                            fa.`media`, 
                                            fa.`thumbnail`, 
                                            fa.`status`
                                        FROM (
                                            SELECT
                                                `id` message_id,
                                                `user_id` id,
                                                `type`,
                                                `user_name` name,
                                                `text`,
                                                `timestamp`,
                                                `sticker`,
                                                `latitude`,
                                                `longitude`
                                            FROM `facebook_messages` 
                                            WHERE
                                                `dev_id` = {$escapedDevId} AND `account` = {$escapedAccount} AND `group_id` = {$escapedGroupId}
                                                {$where}
                                            GROUP BY `timestamp`
                                            ORDER BY `timestamp` DESC
                                            LIMIT {$start}, {$length}
                                        ) fm
                                        LEFT JOIN `facebook_attachment` fa ON fa.`message_id` = fm.`message_id`")->fetchAll();
          
        $list['items'] = $this->wrapMessagesData($messages);
                                            
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
            $sSearch = "(`text` LIKE {$escapedSearch})"; // `phone_number` LIKE {$escapedSearch} OR `number_name` LIKE {$escapedSearch} OR
            $where[] = $sSearch;
        }
        if (count($where) > 0)
            $where = 'AND ' . implode(' AND ', $where);
        else
            $where = '';
        
        $count = $this->getDb()->query("SELECT COUNT(fm.`id`) as count FROM (SELECT `id` FROM `facebook_messages` 
                                        WHERE `dev_id` = {$escapedDevId} AND `account` = {$escapedAccount} AND `group_id` = {$escapedGroupId}
                                        {$where}    
                                        GROUP BY `timestamp` ORDER BY `timestamp` DESC) as fm")->fetch();
        return ($count['count']) ? $count['count'] : false;
    }

}
