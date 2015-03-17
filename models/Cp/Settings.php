<?php

namespace Models\Cp;

use CS\Devices\DeviceOptions;

class Settings extends BaseModel
{

    public static $networksList = array(
        'wifi' => 'Wi-Fi only',
        'any' => 'Wi-Fi and Mobile Network'
    );
    public static $networkFeatures = array(
        'photos' => 'photos_network',
        'videos' => 'video_network'
    );

    public function setNetwork($devId, $feature, $net)
    {
        if (!isset(self::$networkFeatures[$feature])) {
            throw new \Exception('Invalid feature');
        }

        if ($net !== 'any' && $net !== 'wifi') {
            throw new DevicesInvalidNetworkException();
        }

        $column = self::$networkFeatures[$feature];
        $devId = $this->getDB()->quote($devId);
        $net = $this->getDB()->quote($net);

        $this->getDb()->exec("UPDATE `dev_settings` SET `{$column}` = {$net} WHERE `dev_id` = {$devId}");
    }

    public function getNetwork($devId, $feature)
    {
        if (!isset(self::$networkFeatures[$feature])) {
            throw new \Exception('Invalid feature');
        }

        $column = self::$networkFeatures[$feature];
        $devId = $this->getDB()->quote($devId);

        return $this->getDb()->query("SELECT `{$column}` FROM `dev_settings` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn();
    }

    public function addBlackListPhone($devId, $phone)
    {
        if (($blackString = $this->getPhoneBlackListString($devId)) === false) {
            throw new Settings\SettingsNotFoundException("Device black phones list not found");
        }

        if (!validatePhoneNumber($phone)) {
            throw new Settings\InvalidPhoneNumberException();
        }

        $list = $this->buildBlackList($blackString);

        if (in_array($phone, $list)) {
            throw new Settings\PhoneNumberExistException();
        }

        array_push($list, $phone);

        $newString = $this->blackListToString($list);

        return $this->setPhonesBlackListString($devId, $newString);
    }

    public function removeBlackListPhone($devId, $phone)
    {
        if (($blackString = $this->getPhoneBlackListString($devId)) === false) {
            throw new Settings\SettingsNotFoundException("Device black phones list not found");
        }

        $list = $this->buildBlackList($blackString);

        if (($key = array_search($phone, $list)) === false) {
            throw new Settings\PhoneNumberNotFoundInListException();
        }

        unset($list[$key]);
        $value = $this->blackListToString($list);

        return $this->setPhonesBlackListString($devId, $value);
    }

    public function setDeviceSettings($devId, $simNotifications, $blackWords)
    {
        $escapedDevId = $this->getDB()->quote($devId);
        $blackWordsString = $this->getDB()->quote($this->rebuildBlackWordsList($blackWords));
        $escapedSimNotifications = $this->getDB()->quote($simNotifications);

        return $this->getDb()->exec("UPDATE 
                                            `dev_settings`
                                        SET 
                                            `bl_words` = {$blackWordsString},
                                            `sim_notification` = {$escapedSimNotifications}
                                        WHERE
                                            `dev_id` = {$escapedDevId}");
    }

    public function lockDevice($devId, $password)
    {
        if (!preg_match('/^[0-9]{4}$/', $password)) {
            throw new Settings\InvalidPasswordException();
        }

        return $this->setDeviceLockPassword($devId, $password);
    }

    public function setDeviceLockPassword($devId, $password)
    {
        $escapedDevId = $this->getDB()->quote($devId);
        $escapedPassword = intval($password);

        return $this->getDb()->exec("UPDATE `dev_settings` SET `lock_phone` = {$escapedPassword} WHERE `dev_id` = {$escapedDevId} LIMIT 1");
    }

    public function getPhoneBlackListString($devId)
    {
        $escapedDevId = $this->getDB()->quote($devId);

        return $this->getDb()->query("SELECT `bl_phones` FROM `dev_settings` WHERE `dev_id` = {$escapedDevId} LIMIT 1")->fetchColumn();
    }

    public function setRebootDevice($devId)
    {
        $escapedDevId = $this->getDB()->quote($devId);

        return $this->getDb()->exec("UPDATE `dev_settings` SET `reboot_dev` = 1 WHERE `dev_id` = {$escapedDevId} LIMIT 1");
    }

    public function setRebootApp($devId)
    {
        $escapedDevId = $this->getDB()->quote($devId);

        return $this->getDb()->exec("UPDATE `dev_settings` SET `reboot_app` = 1 WHERE `dev_id` = {$escapedDevId} LIMIT 1");
    }

    public function setPhonesBlackListString($devId, $value)
    {
        $escapedDevId = $this->getDB()->quote($devId);
        $escapedValue = $this->getDB()->quote($value);

        return $this->getDb()->exec("UPDATE `dev_settings` SET `bl_phones` = {$escapedValue} WHERE `dev_id` = {$escapedDevId} LIMIT 1");
    }

    protected function rebuildBlackWordsList($string)
    {
        $replacedString = str_replace(array(", ", " ", ",", "\r\n", "\n", "\r"), ",", $string);
        $wordsList = array_unique(explode(",", $replacedString));
        foreach ($wordsList as $key => $value) {
            if (strlen($value) == 0) {
                unset($wordsList[$key]);
                break;
            }
        }
        return implode(",", $wordsList);
    }

    protected function buildBlackList($string)
    {
        $array = explode(',', $string);

        foreach ($array as $key => $value) {
            if (!strlen($value)) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    protected function blackListToString($list)
    {
        return implode(',', $list);
    }

    private function getDeviceSettings($devId)
    {
        $escapedDevId = $this->getDB()->quote($devId);

        return $this->getDb()->query("SELECT * FROM `dev_settings` WHERE `dev_id` = {$escapedDevId} LIMIT 1")->fetch();
    }

    public function getSettings($devId)
    {
        $devInfo = $this->di['currentDevice'];

        if (($settings = $this->getDeviceSettings($devId)) === false) {
            throw new Settings\SettingsNotFoundException("Device settings not found");
        }

        return array(
            'settings' => $settings,
            'blackListPhones' => $this->buildBlackList($settings['bl_phones']),
            'lockActive' => DeviceOptions::isLockActive($devInfo['os'], $devInfo['os_version']),
            'blockSMSActive' => DeviceOptions::isBlockSMSActive($devInfo['os'], $devInfo['os_version']),
            'rebootApplicationActive' => DeviceOptions::isRebootApplicationActive($devInfo['os']),
            'rebootDeviceActive' => DeviceOptions::isRebootDeviceActive($devInfo['os']),
            'isBlackListAvailable' => DeviceOptions::isBlackListAvailable($devInfo['os']),
            'isSimNotificationAvailable' => DeviceOptions::isSimNotificationAvailable($devInfo['os']),
            'isDeviceCommandsAvailable' => DeviceOptions::isDeviceCommandsAvailable($devInfo['os']),
        );
    }

}
