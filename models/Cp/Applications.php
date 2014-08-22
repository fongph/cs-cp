<?php

namespace Models\Cp;

class Applications extends \System\Model {

    public function getDataTableData($devId, $params = array()) {
        $devId = $this->getDb()->quote($devId);

        $search = '';
        if (!empty($params['search'])) {
            $searched = $this->getDb()->quote('%' . $params['search'] . '%');
            $search = "`app_name` LIKE {$searched}";
        }

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['`app_name`', '`app_version`', '`store_url`', '`count`', `lasttime`, '`deleted`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT `app_id` id, `app_name` name, `app_version` version, `store_url` url, `deleted`, `count`, `lasttime`, `is_blocked` blocked";

        $fromWhere = "FROM `applications` WHERE `dev_id` = {$devId}";

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

    function exist($devId, $appId) {
        $devId = $this->getDB()->quote($devId);
        $appId = $this->getDB()->quote($appId);

        return $this->getDb()->query("SELECT COUNT(*) FROM `applications` WHERE `dev_id` = $devId AND `app_id` = $appId")->fetchColumn() > 0;
    }

    function setBlock($devId, $appId) {
        $escapedDevId = $this->getDB()->quote($devId);
        $escapedAppId = $this->getDB()->quote($appId);
        
        if ($this->getDb()->exec("UPDATE `applications` SET `is_blocked` = 1 WHERE `dev_id` = $escapedDevId AND `app_id` = $escapedAppId LIMIT 1") !== 1) {
            if ($this->exist($devId, $appId)) {
                throw new ApplicationsAlreadyBlockedException('Application already blocked');
            }
            
            throw new ApplicationsNotFoundException('Application to block not found');
        }

        return true;
    }

    function setUnblock($devId, $appId) {
        $escapedDevId = $this->getDB()->quote($devId);
        $escapedAppId = $this->getDB()->quote($appId);
        
        if ($this->getDb()->exec("UPDATE `applications` SET `is_blocked` = 0 WHERE `dev_id` = $escapedDevId AND `app_id` = $escapedAppId LIMIT 1") !== 1) {
            if ($this->exist($devId, $appId)) {
                throw new ApplicationsAlreadyUnBlockedException('Application already unblocked');
            }
            
            throw new ApplicationsNotFoundException('Application to unblock not found');
        }

        return true;
    }
    
    public function hasRecords($devId) {
        $devId = $this->getDb()->quote($devId);
        
        return $this->getDb()->query("SELECT `dev_id` FROM `applications` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn() !== false;
    }

}

class ApplicationsNotFoundException extends \Exception {
    
}

class ApplicationsAlreadyBlockedException extends \Exception {
    
}

class ApplicationsAlreadyUnblockedException extends \Exception {
    
}