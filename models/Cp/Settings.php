<?php

namespace Models\Cp;

class Settings extends \System\Model
{

    public function addBlackListPhone($devId, $phone)
    {
        if (($blackString = $this->getPhoneBlackListString($devId)) === false) {
            throw new \Exception("Device black phones list not found");
        }

        if (!validatePhoneNumber($phone)) {
            throw new SettingsInvalidPhoneNumberException();
        }

        $list = $this->_buildBlackList($blackString);

        if (in_array($phone, $list)) {
            throw new SettingsPhoneNumberExistException();
        }

        array_push($list, $phone);

        $newString = $this->_blackListToString($list);

        return $this->setPhonesBlackListString($devId, $newString);
    }

    public function removeBlackListPhone($devId, $phone)
    {
        if (($blackString = $this->getPhoneBlackListString($devId)) === false) {
            throw new \Exception("Device black phones list not found");
        }

        $list = $this->_buildBlackList($blackString);

        if (($key = array_search($phone, $list)) === false) {
            throw new SettingsPhoneNumberNotFoundInListException();
        }

        unset($list[$key]);
        $value = $this->_blackListToString($list);

        return $this->setPhonesBlackListString($devId, $value);
    }

    public function setDeviceSettings($devId, $name, $simNotifications, $blackWordsString)
    {
        if (strlen($name) == 1 || strlen($name) > 32) {
            throw new SettingsInvalidDeviceNameException();
        }

        if ($blackWordsString !== null) {
            $blackWordsString = $this->_rebuildBlackWordsList($blackWordsString);
        } else {
            $blackWordsString = '';
        }

        $devId = $this->getDB()->quote($devId);
        $name = $this->getDB()->quote($name);
        $blackWordsString = $this->getDB()->quote($blackWordsString);
        $simNotifications = $this->getDB()->quote($simNotifications);

        return $this->getDb()->exec("UPDATE `user_dev` SET `ident` = {$name} WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("UPDATE `dev_settings` SET `bl_words` = {$blackWordsString}, `sim_notification` = {$simNotifications} WHERE `dev_id` = {$devId}");
    }

    public function lockDevice($devId, $password)
    {
        if (!preg_match('/^[0-9]{4}$/', $password)) {
            throw new SettingsInvalidPasswordException();
        }

        return $this->setDeviceLockPassword($devId, $password);
    }

    public function setDeviceLockPassword($devId, $password)
    {
        $devId = $this->getDB()->quote($devId);
        $password = intval($password);

        return $this->getDb()->exec("UPDATE `dev_settings` SET `lock_phone` = {$password} WHERE `dev_id` = {$devId} LIMIT 1");
    }

    public function getPhoneBlackListString($devId)
    {
        $devId = $this->getDB()->quote($devId);

        return $this->getDb()->query("SELECT `bl_phones` FROM `dev_settings` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn();
    }

    public function setRebootDevice($devId)
    {
        $devId = $this->getDB()->quote($devId);

        return $this->getDb()->exec("UPDATE `dev_settings` SET `reboot_dev` = 1 WHERE `dev_id` = {$devId}");
    }

    public function setRebootApp($devId)
    {
        $devId = $this->getDB()->quote($devId);

        return $this->getDb()->exec("UPDATE `dev_settings` SET `reboot_app` = 1 WHERE `dev_id` = {$devId}");
    }

    public function setPhonesBlackListString($devId, $value)
    {
        $devId = $this->getDB()->quote($devId);
        $value = $this->getDB()->quote($value);

        return $this->getDb()->exec("UPDATE `dev_settings` SET `bl_phones` = {$value} WHERE `dev_id` = {$devId}");
    }

    public function delete($devId)
    {
        $devicesModel = new \Models\Devices($this->di);

        return $devicesModel->delete($devId) & $devicesModel->deleteFiles($devId);
    }

    protected function _rebuildBlackWordsList($string)
    {
        $replacedString = str_replace(array(", ", " ", ",", "\r\n", "\n", "\r"), ",", $string);
        $wordsList = array_unique(explode(",", $replacedString));
        return implode(",", $wordsList);
    }

    protected function _buildBlackList($string)
    {
        $array = explode(',', $string);

        foreach ($array as $key => $value) {
            if (!strlen($value)) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    protected function _blackListToString($list)
    {
        return implode(',', $list);
    }

    public function getSettings($devId)
    {
        $devicesModel = new \Models\Devices($this->di);

        if (($settings = $devicesModel->getSettings($devId)) === false) {
            throw new \Exception("Device settings not found");
        }

        if (($devInfo = $devicesModel->getDeviceInfo($devId)) === false) {
            throw new \Exception("Device info not found");
        }

        if (($plan = $devicesModel->getPlan($devId)) === false) {
            throw new \Exception("Plan info not found");
        }

        return array(
            'settings' => $settings,
            'devInfo' => $devInfo,
            'plan' => $plan,
            'blackListPhones' => $this->_buildBlackList($settings['bl_phones']),
            'online' => ($settings['last_visit'] > time() - 20 * 60),
            'lockActive' => $this->isLockFunctionalActive($devInfo['os'], $devInfo['os_version'], $devInfo['app_version']),
            'reloadActive' => $this->isReloadFunctionalActive($devInfo['os'], $devInfo['app_version'])
        );
    }

    /**
     * Check that lock functional work on device
     * 
     * android +
     * ios < 7.1 +
     * ios >= 7.1 with app version > 42 +
     * 
     * @param type $os
     * @param type $osVersion
     * @param type $appVersion
     * @return boolean
     */
    private function isLockFunctionalActive($os, $osVersion, $appVersion)
    {
        if ($os === 'android') {
            return true;
        }
        
        if ($os === 'ios') {
            $osVersion = str_replace('.', '', $osVersion);
            if ($osVersion < 100) {
                $osVersion *= 10;
            }
            
            if ($osVersion < 710) {
                return true;
            } elseif ($appVersion >= 42) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check that reload functional work on device
     * 
     * android with app version > 63 +
     * 
     * @param type $os
     * @param type $osVersion
     * @param type $appVersion
     * @return boolean
     */
    private function isReloadFunctionalActive($os, $appVersion)
    {
        if ($os === 'android' && $appVersion > 63) {
            return true;
        }
        
        return false;
    }

}

class SettingsInvalidPhoneNumberException extends \Exception
{
    
}

class SettingsPhoneNumberExistException extends \Exception
{
    
}

class SettingsPhoneNumberNotFoundInListException extends \Exception
{
    
}

class SettingsInvalidDeviceNameException extends \Exception
{
    
}

class SettingsInvalidPasswordException extends \Exception
{
    
}
