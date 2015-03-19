<?php

namespace Models\Cp;

class Sms extends BaseModel {

    public function getDataTableData($devId, $params = array()) {
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

        $select = "SELECT `timestamp`, `sms_type` type, `phone_number` number, `number_name` name, LEFT(`content`, 201) `content`, `blocked`";

        $timeFrom = $this->getDb()->quote($params['timeFrom']);
        $timeTo = $this->getDb()->quote($params['timeTo']);
        $deleted = $this->getDb()->quote($params['deleted']);
        $fromWhere = "FROM `sms_log` WHERE `dev_id` = {$devId} AND `timestamp` >= {$timeFrom} AND `timestamp` <= {$timeTo} AND `deleted` = {$deleted}";

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

    public function hasRecords($devId) {
        $devId = $this->getDb()->quote($devId);
        return $this->getDb()->query("SELECT `dev_id` FROM `sms_log` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn() !== false;
    }
    
    public function getPhoneSmsList($devId, $phoneNumber) {
        $devId = $this->getDb()->quote($devId);
        $phoneNumber = $this->getDb()->quote($phoneNumber);

        return $this->getDb()->query("SELECT 
                                        `sms_type` type,
                                        `number_name` name,
                                        `phone_number` phone,
                                        `content`,
                                        `timestamp`,
                                        `blocked`,
                                        `deleted`
                                    FROM `sms_log` WHERE 
                                        `dev_id` = {$devId} AND 
                                        `phone_number` = {$phoneNumber}
                                    ORDER BY 
                                        `timestamp` DESC")->fetchAll();
    }

}
