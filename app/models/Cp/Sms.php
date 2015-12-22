<?php

namespace Models\Cp;

class Sms extends BaseModel
{

    private static $_authLifeTime = 3600;

    public function getCDNAuthorizedUrl($uri)
    {
        $s3 = $this->di->get('S3');
        return $s3->getSignedCannedURL($this->di['config']['cloudFront']['domain'] . substr($uri, 1), self::$_authLifeTime);
    }

    public function getDataTableData($devId, $params = array())
    {
        $devId = $this->getDb()->quote($devId);

        $search = '';
        if (!empty($params['search'])) {
            $searched = $this->getDb()->quote('%' . $params['search'] . '%');
            $search = "`phone_number` LIKE {$searched} OR `number_name` LIKE {$searched} OR `content` LIKE {$searched}";
        }

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['`timestamp`', '`sms_type`', '`phone_number`', '`number_name`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT `timestamp`, `sms_type` type, `phone_number` number, `number_name` name, LEFT(`content`, 201) `content`, `multimedia`, `group`, `blocked`, `deleted`";

        if ($params['timeFrom'] > 0 && $params['timeTo'] > 0) {
            $timeFrom = $this->getDb()->quote($params['timeFrom']);
            $timeTo = $this->getDb()->quote($params['timeTo']);
            $fromWhere = "FROM `sms_log` WHERE `dev_id` = {$devId} AND `timestamp` >= {$timeFrom} AND `timestamp` <= {$timeTo}";
        } else {
            $fromWhere = "FROM `sms_log` WHERE `dev_id` = {$devId}";
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
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) {$fromWhere}")->fetchColumn();

            if ($search) {
                $result['iTotalDisplayRecords'] = $this->getDb()->query("SELECT COUNT(*) {$fromWhere} AND ({$search})")->fetchColumn();
            } else {
                $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
            }
        }

        return $result;
    }

    public function hasRecords($devId)
    {
        $devId = $this->getDb()->quote($devId);
        return $this->getDb()->query("SELECT `dev_id` FROM `sms_log` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn() !== false;
    }

    public function getLastTimestamp($devId)
    {
        $devId = $this->getDb()->quote($devId);
        return $this->getDb()->query("SELECT `timestamp` FROM `sms_log` WHERE `dev_id` = {$devId} GROUP BY `timestamp` ORDER BY `timestamp` DESC LIMIT 1")->fetch();
    }

    public function getPhoneSmsList($devId, $phoneNumber)
    {
        $devId = $this->getDb()->quote($devId);
        $phoneNumber = $this->getDb()->quote($phoneNumber);

        return $this->getDb()->query("SELECT
                                        `sms_type` type,
                                        `number_name` name,
                                        `phone_number` phone,
                                        `content`,
                                        `timestamp`,
                                        `multimedia`,
                                        `blocked`,
                                        `deleted`,
                                        `network`
                                    FROM `sms_log` WHERE
                                        `dev_id` = {$devId} AND
                                        `phone_number` = {$phoneNumber} AND
                                        `group` = ''
                                    ORDER BY
                                        `timestamp` DESC")->fetchAll();
    }

    public function getPhoneGroupSmsList($devId, $group)
    {
        $devId = $this->getDb()->quote($devId);
        $group = $this->getDb()->quote($group);

        return $this->getDb()->query("SELECT
                                        `sms_type` type,
                                        `number_name` name,
                                        `phone_number` phone,
                                        `content`,
                                        `timestamp`,
                                        `multimedia`,
                                        `blocked`,
                                        `deleted`,
                                        `network`
                                    FROM `sms_log` WHERE
                                        `dev_id` = {$devId} AND
                                        `group` = {$group}
                                    ORDER BY
                                        `timestamp` DESC")->fetchAll();
    }

    public function getGroupMembers($devId, $group)
    {
        $devId = $this->getDb()->quote($devId);
        $group = $this->getDb()->quote($group);

        return $this->getDb()->query("SELECT `number_name` name, `phone_number` phone  FROM `sms_group_members` WHERE `dev_id` = {$devId} AND `group` = {$group}")->fetchAll();
    }

    public function parseCard($data)
    {
        $card = \Sabre\VObject\Reader::read($data);

        $result = [
            'params' => []
        ];

        if (isset($card->fn)) {
            $result['params']['Name'] = $card->fn->getValue();
        }

        if (isset($card->tel)) {
            foreach ($card->tel as $phone) {
                $result['params']['Phone'][] = $phone->getValue();
            }
        }

        if (isset($card->email)) {
            foreach ($card->email as $email) {
                $result['params']['Email'][] = $email->getValue();
            }
        }

        if (isset($card->org)) {
            $result['params']['Organization'] = $card->org->getValue();
        }

        if (isset($card->photo, $card->photo->parameters['TYPE'])) {
            switch ($card->photo->parameters['TYPE']) {
                case 'JPEG':
                    $result['photo'] = 'data:image/jpeg;base64,' . $card->photo->getRawMimeDirValue();
                    break;
                case 'GIF':
                    $result['photo'] = 'data:image/gif;base64,' . $card->photo->getRawMimeDirValue();
                    break;
                case 'PNG':
                    $result['photo'] = 'data:image/png;base64,' . $card->photo->getRawMimeDirValue();
                    break;
            }
        }

        return $result;
    }

    private function postProcessing($items) {
        foreach ($items as $key => $item) {
            if ($item['multimedia'] == 'vcard' && strlen($item['media_data'])) {
                $items[$key]['card'] = $this->parseCard($item['media_data']);
            } else if ($item['multimedia'] == 'image' && isset($item['media_path'], $item['thumbnail_path'])) {
                $items[$key]['image'] = $this->getCDNAuthorizedUrl($item['media_path']);
                $items[$key]['thumbnail'] = $this->getCDNAuthorizedUrl($item['thumbnail_path']);
            }

            unset($items[$key]['media_data']);
            unset($items[$key]['media_path']);
            unset($items[$key]['thumbnail_path']);
        }

        return $items;
    }

    /**
     * Paginate
     */
    public function getDataPhoneSmsList($devId, $phoneNumber, $search, $page = 0, $length = 10)
    {
        $where = array();
        $escapedDevId = $this->getDb()->quote($devId);
        $escapedPhoneNumber = $this->getDb()->quote($phoneNumber);

        $start = ($page <= 0 ) ? 0 : $page - 1;
        $start *= $length;

        $sSearch = "";
        if (!empty($search)) {
            $escapedSearch = $this->getDb()->quote("%{$search}%");
            $sSearch = "(`content` LIKE {$escapedSearch})";
            $where[] = $sSearch;
        }
        if (count($where) > 0)
            $where = 'AND ' . implode(' AND ', $where);
        else
            $where = '';

        $list['items'] = $this->getDb()->query("SELECT
                                                    s.`sms_type` type,
                                                    s.`number_name` name,
                                                    s.`phone_number` phone,
                                                    s.`content`,
                                                    s.`timestamp`,
                                                    s.`multimedia`,
                                                    s.`blocked`,
                                                    s.`deleted`,
                                                    s.`network`,
                                                    sm.`data` media_data,
                                                    sm.`media` media_path,
                                                    sm.`thumbnail` thumbnail_path
                                                FROM `sms_log` s
                                                LEFT JOIN `sms_multimedia` sm ON s.`dev_id` = sm.`dev_id` AND s.`multimedia_id` = sm.`multimedia_id`
                                                WHERE s.`dev_id` = {$escapedDevId} AND s.`phone_number` = {$escapedPhoneNumber} AND s.`group` = ''
                                                {$where}
                                                ORDER BY s.`timestamp` DESC LIMIT {$start}, {$length}")->fetchAll();

        $list['items'] = $this->postProcessing($list['items']);

        $count = $this->getCountDataPhoneSmsList($devId, $phoneNumber, $search);
        $list['totalPages'] = ($count) ? ceil($count / $length) : false;
        $list['countEnteres'] = (!empty($search)) ? $this->getCountDataPhoneSmsList($devId, $phoneNumber, false) : 0;
        $list['countItem'] = $count;

        return $list;
    }

    public function getDataPhoneGroupSmsList($devId, $group, $search, $page = 0, $length = 10)
    {
        $where = array();
        $escapedDevId = $this->getDb()->quote($devId);
        $escapedGroup = $this->getDb()->quote($group);

        $start = ($page <= 0 ) ? 0 : $page - 1;
        $start *= $length;

        $sSearch = "";
        if (!empty($search)) {
            $escapedSearch = $this->getDb()->quote("%{$search}%");
            $sSearch = "(`content` LIKE {$escapedSearch})";
            $where[] = $sSearch;
        }
        if (count($where) > 0)
            $where = 'AND ' . implode(' AND ', $where);
        else
            $where = '';

        $list['items'] = $this->getDb()->query("SELECT
                                                    s.`sms_type` type,
                                                    s.`number_name` name,
                                                    s.`phone_number` phone,
                                                    s.`content`,
                                                    s.`timestamp`,
                                                    s.`multimedia`,
                                                    s.`blocked`,
                                                    s.`deleted`,
                                                    s.`network`,
                                                    sm.`data` media_data,
                                                    sm.`media` media_path,
                                                    sm.`thumbnail` thumbnail_path
                                                FROM `sms_log` s
                                                LEFT JOIN `sms_multimedia` sm ON s.`dev_id` = sm.`dev_id` AND s.`multimedia_id` = sm.`multimedia_id`
                                                WHERE s.`dev_id` = {$escapedDevId} AND s.`group` = {$escapedGroup}
                                                {$where}
                                                ORDER BY s.`timestamp` DESC LIMIT {$start}, {$length}")->fetchAll();

        $list['items'] = $this->postProcessing($list['items']);

        $count = $this->getCountDataPhoneGroupSmsList($devId, $group, $search);
        $list['totalPages'] = ($count) ? ceil($count / $length) : false;
        $list['countEnteres'] = (!empty($search)) ? $this->getCountDataPhoneGroupSmsList($devId, $group, false) : 0;
        $list['countItem'] = $count;

        return $list;
    }

    public function getCountDataPhoneSmsList($devId, $phoneNumber, $search)
    {
        $where = array();
        $escapedDevId = $this->getDb()->quote($devId);
        $escapedPhoneNumber = $this->getDb()->quote($phoneNumber);

        $sSearch = "";
        if (!empty($search)) {
            $escapedSearch = $this->getDb()->quote("%{$search}%");
            $sSearch = "(`content` LIKE {$escapedSearch})";
            $where[] = $sSearch;
        }
        if (count($where) > 0)
            $where = 'AND ' . implode(' AND ', $where);
        else
            $where = '';

        $count = $this->getDb()->query("SELECT COUNT(`id`) as count FROM `sms_log` WHERE `dev_id` = {$escapedDevId} AND `phone_number` = {$escapedPhoneNumber} AND `group` = '' {$where} ORDER BY `timestamp` DESC")->fetch();
        return ($count['count']) ? $count['count'] : false;
    }

    public function getCountDataPhoneGroupSmsList($devId, $group, $search)
    {
        $where = array();
        $escapedDevId = $this->getDb()->quote($devId);
        $escapedGroup = $this->getDb()->quote($group);

        $sSearch = "";
        if (!empty($search)) {
            $escapedSearch = $this->getDb()->quote("%{$search}%");
            $sSearch = "(`content` LIKE {$escapedSearch})";
            $where[] = $sSearch;
        }
        if (count($where) > 0)
            $where = 'AND ' . implode(' AND ', $where);
        else
            $where = '';

        $count = $this->getDb()->query("SELECT COUNT(`id`) as count FROM `sms_log` WHERE `dev_id` = {$escapedDevId} AND `group` = {$escapedGroup} {$where} ORDER BY `timestamp` DESC")->fetch();
        return ($count['count']) ? $count['count'] : false;
    }

}
