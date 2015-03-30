<?php

namespace Models\Cp;

class BrowserHistory extends BaseModel {

    public function getDataTableData($devId, $params = array()) {
        $devId = $this->getDb()->quote($devId);

        $search = '';
        if (!empty($params['search'])) {
            $searched = $this->getDb()->quote('%' . $params['search'] . '%');
            $search = "`title` LIKE {$searched} OR `url` LIKE {$searched}";
        }

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['`timestamp`', '`title`', '`url`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT `timestamp`, `title`, `url`, `url`";

        $timeFrom = $this->getDb()->quote($params['timeFrom']);
        $timeTo = $this->getDb()->quote($params['timeTo']);
        $fromWhere = "FROM `browser_history` WHERE `dev_id` = {$devId} AND `timestamp` >= {$timeFrom} AND `timestamp` <= {$timeTo}";

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

    public function getLockedDataTableData($devId, $params = array()) {
        if (!$devId)
            return null;

        $devId = $this->getDb()->quote($devId);

        $search = '';
        if (!empty($params['filter'])) {
            $searched = $this->getDb()->quote('%' . $params['filter'] . '%');
            $search = "`title` LIKE %{$searched}% OR `url` LIKE %{$searched}%";
        }

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['`domain`', '`count`', `lasttime`];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT `domain`, `count`, `lasttime`, `active`";
        $fromWhere = "FROM `browser_blocked` WHERE `dev_id` = {$devId} AND `unblocked` = 0";

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

    public function getHostFromUrl($url) {
        if (($host = parse_url($url, PHP_URL_HOST)) !== null) {
            return $host;
        }

        if (($host = parse_url('http://' . $url, PHP_URL_HOST)) !== null) {
            return $host;
        }

        return false;
    }

    public function exist($devId, $host) {
        $devId = $this->getDB()->quote($devId);
        $host = $this->getDB()->quote($host);

        return $this->getDb()->query("SELECT COUNT(*) FROM `browser_blocked` WHERE `dev_id` = $devId AND `domain` = $host")->fetchColumn() > 0;
    }

    public function addSiteBlock($devId, $domain) {
        if (($host = $this->getHostFromUrl($domain)) === false) {
            throw new BrowserHistory\InvalidDomainNameException('Invalid domain name');
        }

        $escapedDevId = $this->getDB()->quote($devId);
        $escapedHost = $this->getDB()->quote($host);

        if (!$this->getDb()->exec("INSERT INTO `browser_blocked` SET `dev_id` =  {$escapedDevId}, `domain` = {$escapedHost}, `unblocked` = 0 ON DUPLICATE KEY UPDATE `unblocked` = 0")) {
            throw new BrowserHistory\DomainAlreadyExistsException('Domain already exist');
        }

        return true;
    }

    public function addSiteUnblock($devId, $domain) {
        if (($host = $this->getHostFromUrl($domain)) === false) {
            throw new BrowserHistory\InvalidDomainNameException('Invalid domain name');
        }

        $escapedDevId = $this->getDB()->quote($devId);
        $escapedHost = $this->getDB()->quote($host);

        if ($this->getDb()->exec("UPDATE `browser_blocked` SET `unblocked` = 1 WHERE `dev_id` = {$escapedDevId} AND `domain` = {$escapedHost} LIMIT 1") !== 1) {
            if ($this->exist($devId, $host)) {
                throw new BrowserHistory\DomainAlreadyUnblockedException('Domain already unblocked');
            }
            
            throw new BrowserHistory\UndefinedException('Domain to unblock not found');
        }
        
        return true;
    }

    public function hasRecords($devId) {
        $devId = $this->getDb()->quote($devId);

        return $this->getDb()->query("SELECT `dev_id` FROM `browser_history` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn() !== false;
    }

}