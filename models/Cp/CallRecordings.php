<?php

namespace Models\Cp;

class CallRecordings extends \System\Model {

    public static $maxSurroundingLength = 1800;
    private static $_authLifeTime = 3600;

    public function getPlayUrl($devId, $timestamp) {
        $s3 = $this->di->get('S3');
        return $s3->getAuthenticatedURL($this->di['config']['s3']['bucket'], urlencode($devId) . '/call_recording/' . urlencode($timestamp) . '.mp3', self::$_authLifeTime);
    }

    public function getDownloadUrl($devId, $timestamp) {
        $s3 = $this->di->get('S3');
        return $s3->getAuthenticatedURL($this->di['config']['s3']['bucket'], urlencode($devId) . '/call_recording/' . urlencode($timestamp) . '.mp3', self::$_authLifeTime, false, false, array(
                    'response-content-disposition' => 'attachment; filename=' . urlencode($timestamp) . '.mp3'
        ));
    }

    public function getDataTableData($devId, $params = array()) {
        $devId = $this->getDb()->quote($devId);

        $search = '';
        if (!empty($params['search'])) {
            $searched = $this->getDb()->quote('%' . $params['search'] . '%');
            $search = "`phone_number` LIKE {$searched} OR `number_name` LIKE {$searched}";
        }

        $sort = '`timestamp` ASC';
        if (count($params['sortColumns'])) {
            $columns = ['`timestamp`', '`phone_number`', '`number_name`', '`duration`', '`status`'];

            $sort = '';
            foreach ($params['sortColumns'] as $column => $direction) {
                if (isset($columns[$column])) {
                    $sort .= " {$columns[$column]} {$direction}";
                }
            }
        }

        $select = "SELECT `timestamp`, `phone_number` phone, `number_name` name, `recorded`, `status`, `error`";

        if (isset($params['timeFrom'], $params['timeTo'])) {
            $timeFrom = $this->getDb()->quote($params['timeFrom']);
            $timeTo = $this->getDb()->quote($params['timeTo']);
            $fromWhere = "FROM `call_log` WHERE `dev_id` = {$devId} AND `should_be_recorded` = 1 AND `duration` > 0 AND (`recorded` > 0 OR (`status` > 0 OR `error` > 0)) AND `timestamp` >= {$timeFrom} AND `timestamp` <= {$timeTo}";
        }

        $query = "{$select} {$fromWhere}"
                . ($search ? " AND ({$search})" : '')
                . " ORDER BY {$sort} LIMIT {$params['start']}, {$params['length']}";

        //p($query, 1);

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

    public function getPhoneNumbersList($devId) {
        $value = $this->getRecordingPhonesValue($devId);

        if (strlen($value)) {
            if ($value !== 'all') {
                return $this->_buildPhonesList($value);
            }
        } else {
            return 'noRecord';
        }

        return $value;
    }

    protected function _buildPhonesList($string) {
        $array = explode(',', $string);

        foreach ($array as $key => $value) {
            if (!strlen($value)) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    protected function _phoneListToString($list) {
        return implode(',', $list);
    }

    public function getRecordingPhonesValue($devId) {
        $devId = $this->getDB()->quote($devId);

        return $this->getDb()->query("SELECT `rec_phones` FROM `dev_settings` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn();
    }

    public function setRecordingPhonesValue($devId, $value) {
        $devId = $this->getDB()->quote($devId);
        $value = $this->getDB()->quote($value);

        return $this->getDb()->exec("UPDATE `dev_settings` SET `rec_phones` = {$value} WHERE `dev_id` = {$devId}");
    }

    public function addPhoneNumber($devId, $phone) {
        if (($value = $this->getRecordingPhonesValue($devId)) === false) {
            throw new \Exception("Device black phones list not found");
        }

        if (!validatePhoneNumber($phone)) {
            throw new CallRecordingInvalidPhoneNumberException();
        }

        if ($value != 'all') {
            $list = $this->_buildPhonesList($value);

            if (in_array($phone, $list)) {
                throw new CallRecordingPhoneNumberExistException();
            }
        } else {
            $list = array();
        }

        array_push($list, $phone);

        $newString = $this->_phoneListToString($list);

        return $this->setRecordingPhonesValue($devId, $newString);
    }

    public function removePhoneNumber($devId, $phone) {
        if (($value = $this->getRecordingPhonesValue($devId)) === false) {
            throw new \Exception("Device black phones list not found");
        }

        if (!validatePhoneNumber($phone)) {
            throw new CallRecordingInvalidPhoneNumberException();
        }

        $list = $this->_buildPhonesList($value);

        if (($key = array_search($phone, $list)) === false) {
            throw new SettingsPhoneNumberNotFoundInListException();
        }

        unset($list[$key]);
        $newString = $this->_phoneListToString($list);

        return $this->setRecordingPhonesValue($devId, $newString);
    }

    public function setRecordAllPhones($devId) {
        if (($blackString = $this->getRecordingPhonesValue($devId)) === false) {
            throw new \Exception("Device black phones list not found");
        }

        return $this->setRecordingPhonesValue($devId, 'all');
    }
    
    //@TODO: delete files
    public function delete($devId, $start) {
        $devId = $this->getDB()->quote($devId);
        $start = $this->getDB()->quote($start);
        
        if ($this->getDb()->exec("UPDATE `call_log` SET `should_be_recorded` = 0 WHERE `dev_id` = {$devId} AND `timestamp` = {$start} LIMIT 1") !== 1) {
            throw new CallRecordingNotFoundException('Record to delete not found');
        }
        
        return true;
    }
    
    public function hasRecords($devId) {
        $devId = $this->getDb()->quote($devId);
        
        return $this->getDb()->query("SELECT `dev_id` FROM `call_log` WHERE `dev_id` = {$devId} AND `should_be_recorded` = 1 AND `duration` > 0 AND (`recorded` > 0 OR (`status` > 0 OR `error` > 0)) LIMIT 1")->fetchColumn() !== false;
    }
}

class CallRecordingInvalidPhoneNumberException extends \Exception {
    
}

class CallRecordingPhoneNumberExistException extends \Exception {
    
}

class CallRecordingPhoneNumberNotFoundInListException extends \Exception {
    
}

class CallRecordingInvalidDeviceNameException extends \Exception {
    
}

class CallRecordingInvalidPasswordException extends \Exception {
    
}

class CallRecordingNotFoundException extends \Exception {
    
}