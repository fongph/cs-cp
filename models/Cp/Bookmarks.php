<?php

namespace Models\Cp;

class Bookmarks extends \System\Model {

    private static $_standartBrowsers = array('Internet', 'Safari');
    
    public function getDataTableData($devId, $params = array()) {
        if (!$devId)
            return null;

        $devId = $this->getDb()->quote($devId);

        $search = '';
        if (!empty($params['search'])) {
            $searched = $this->getDb()->quote('%' . $params['search'] . '%');
            $search = "`title` LIKE {$searched} OR `url` LIKE {$searched}";
        }

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['`browser`', '`title`', '`url`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT `browser`, `title`, `url`";

        if ($params['deleted']) {
            $fromWhere = "FROM `browser_bookmarks` WHERE `dev_id` = {$devId} AND `deleted` = 1";
        } else {
            $fromWhere = "FROM `browser_bookmarks` WHERE `dev_id` = {$devId} AND `deleted` = 0";
        }

        $query = "{$select} {$fromWhere}"
                . ($search ? " AND ({$search})" : '')
                . " ORDER BY {$sort} LIMIT {$params['start']}, {$params['length']}";

        $result = array(
            'aaData' => $this->getDb()->query($query)->fetchAll(\PDO::FETCH_NUM)
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
        
        return $this->getDb()->query("SELECT `dev_id` FROM `browser_bookmarks` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn() !== false;
    }
    
}
