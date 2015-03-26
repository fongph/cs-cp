<?php

namespace Models\Cp;

class Emails extends BaseModel {

    const PATH_ALL_KEY = '*ALL*';
    
    const PATH_INBOX = 'inbox';
    const PATH_SENT = 'sent';
    const PATH_TRASH = 'trash';

    public function getDataTableData($devId, $params = array()) {
        $devId = $this->getDb()->quote($devId);

        if (count($params['sortColumns'])) {
            if ($params['path'] === self::PATH_SENT) {
                $columns = ['e.`timestamp`', 'e.`to`', 'e.`subject`'];
            } else {
                $columns = ['e.`timestamp`', 'e.`from`', 'e.`subject`'];
            }

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        } else {
            $sort = '`timestamp` ASC';
        }

        if ($params['path'] === self::PATH_SENT) {
            $select = "SELECT e.`timestamp`, e.`to`, e.`subject`";
        } else {
            $select = "SELECT e.`timestamp`, e.`from`, e.`subject`";
        }

        $timeFrom = $this->getDb()->quote($params['timeFrom']);
        $timeTo = $this->getDb()->quote($params['timeTo']);
        $account = $this->getDb()->quote($params['account']);

        if ($params['path'] === self::PATH_ALL_KEY) {
            $fromWhere = "FROM `emails` e
                          WHERE
                                e.`dev_id` = {$devId} AND
                                e.`account` = {$account} AND
                                e.`timestamp` >= {$timeFrom} AND
                                e.`timestamp` <= {$timeTo}";
        } else {
            $path = $this->getDb()->quote($params['path']);

            $fromWhere = "FROM `emails` e
                          WHERE
                                e.`dev_id` = {$devId} AND
                                e.`account` = {$account} AND
                                e.`parent` = {$path} AND
                                e.`timestamp` >= {$timeFrom} AND
                                e.`timestamp` <= {$timeTo}";
        }


        $query = "{$select} {$fromWhere}"
                . " ORDER BY {$sort} LIMIT {$params['start']}, {$params['length']}";

        $result = array(
            'aaData' => $this->getDb()->query($query)->fetchAll(\PDO::FETCH_NUM)
        );

        if (empty($result['aaData'])) {
            $result['iTotalRecords'] = 0;
            $result['iTotalDisplayRecords'] = 0;
        } else {
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) FROM (SELECT e.`timestamp` {$fromWhere}) a")->fetchColumn();
            $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
        }

        return $result;
    }

    public function getPathsList($devId, $account) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);

        $list = $this->getDb()->query("SELECT DISTINCT `parent` FROM `emails` WHERE `dev_id` = {$devId} AND `account` = {$account} ORDER BY `parent`")->fetchAll(\PDO::FETCH_COLUMN);
        $result = array_combine($list, $list);

        return array_merge(array(
            self::PATH_ALL_KEY => $this->di['t']->_('All')
                ), $result);
    }

    public function getAccountsList($devId) {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT DISTINCT `account` FROM `emails` WHERE `dev_id` = {$devId} ORDER BY `account`")->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getEmailView($devId, $account, $timestamp) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $timestamp = $this->getDb()->quote($timestamp);

        return $this->getDb()->query("SELECT `timestamp`, `from`, `to`, `subject` FROM `emails` WHERE `dev_id` = {$devId} AND `account` = {$account} AND `timestamp` = {$timestamp} LIMIT 1")->fetch();
    }

    public function getEmailContent($devId, $account, $timestamp) {
        $devId = $this->getDb()->quote($devId);
        $account = $this->getDb()->quote($account);
        $timestamp = $this->getDb()->quote($timestamp);

        return $this->getDb()->query("SELECT `text` FROM `emails` WHERE `dev_id` = {$devId} AND `account` = {$account} AND `timestamp` = {$timestamp} LIMIT 1")->fetchColumn();
    }

    public function replaceImageSrc($content) {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        $dom->loadHTML('<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . $content);
        $images = $dom->getElementsByTagName("img");
        foreach ($images as $item) {
            $item->setAttribute('tmp-src', $item->getAttribute('src'));
            $item->setAttribute('src', '');
        }

        return $dom->saveHTML();
    }

}
