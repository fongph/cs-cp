<?php

namespace Models\Cp;

class Notes extends BaseModel
{

    public function getDataTableData($devId, $params = array())
    {
        $devId = $this->getDb()->quote($devId);

        if (count($params['sortColumns'])) {
            $columns = ['`timestamp`', '`from`', '`subject`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        } else {
            $sort = '`timestamp` ASC';
        }

        $select = "SELECT `timestamp`, `title`, LEFT(`content`, 201) `content`";

        $timeFrom = $this->getDb()->quote($params['timeFrom']);
        $timeTo = $this->getDb()->quote($params['timeTo']);

        $fromWhere = "FROM `notes`
                      WHERE
                            `dev_id` = {$devId} AND
                            `timestamp` >= {$timeFrom} AND
                            `timestamp` <= {$timeTo}";

        $query = "{$select} {$fromWhere}"
                . " ORDER BY {$sort} LIMIT {$params['start']}, {$params['length']}";

        $result = array(
            'aaData' => $this->getDb()->query($query)->fetchAll(\PDO::FETCH_NUM)
        );

        if (empty($result['aaData'])) {
            $result['iTotalRecords'] = 0;
            $result['iTotalDisplayRecords'] = 0;
        } else {
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT `timestamp` {$fromWhere}) a")->fetchColumn();
            $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
        }

        return $result;
    }

    public function getAccountsList($devId)
    {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT DISTINCT `account` FROM `notes` WHERE `dev_id` = {$devId} ORDER BY `account`")->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getNote($devId, $account, $timestamp)
    {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $timestamp = $this->getDb()->quote($timestamp);

        return $this->getDb()->query("SELECT `timestamp`, `title`, `content` FROM `notes` WHERE `dev_id` = {$devId} AND `account` = {$account} AND `timestamp` = {$timestamp} LIMIT 1")->fetch();
    }

}
