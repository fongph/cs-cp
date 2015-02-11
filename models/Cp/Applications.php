<?php

namespace Models\Cp;

class Applications extends BaseModel {

    public function getDataTableData($devId, $params = array()) {
        $devId = $this->getDb()->quote($devId);

        $search = '';
        if (!empty($params['search'])) {
            $searched = $this->getDb()->quote('%' . $params['search'] . '%');
            $search = "`app_name` LIKE {$searched}";
        }

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['`app_name`', '`app_version`', '`store_url`', '`count`', '`lasttime`', '`app_name`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT a.`app_id` id, a.`app_name` name, a.`app_version` version, a.`store_url` url, a.`deleted`, COUNT(t.id) as count, MAX(t.`start`) lasttime, a.`is_blocked` blocked";

        $fromWhere = "FROM `applications` a
            LEFT JOIN `applications_timelines` t ON a.`dev_id` = t.`dev_id` AND a.`app_id` = t.`name`
            WHERE a.`dev_id` = {$devId}";

        $query = "{$select} {$fromWhere}"
                . ($search ? " AND ({$search})" : '')
                . " GROUP BY a.`app_id` ORDER BY {$sort} LIMIT {$params['start']}, {$params['length']}";

        $result = array(
            'aaData' => $this->getDb()->query($query)->fetchAll(\PDO::FETCH_ASSOC)
        );
        
        $countQueryWhere = "FROM `applications` a WHERE a.`dev_id` = {$devId}";

        if (empty($result['aaData'])) {
            $result['iTotalRecords'] = 0;
            $result['iTotalDisplayRecords'] = 0;
        } else {
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) {$countQueryWhere}")->fetchColumn();

            if ($search) {
                $result['iTotalDisplayRecords'] = $this->getDb()->query("SELECT COUNT(*) {$countQueryWhere} AND ({$search})")->fetchColumn();
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