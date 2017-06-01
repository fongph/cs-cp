<?php

namespace Models;

use CS\Devices\Manager as DevicesManager,
    CS\Devices\Limitations;
use CS\Devices\Manager;
use CS\Models\Device\DeviceRecord;
use CS\Models\License\LicenseRecord;
use CS\Models\Product\ProductRecord;

class Devices extends \System\Model {

    private $limitation;

    public function setDeviceName($devId, $name)
    {
        if (strlen($name) < 1 || strlen($name) > 32) {
            throw new Devices\InvalidDeviceNameException();
        }

        $devicesManager = new DevicesManager($this->getDb());

        $devicesManager->getDevice($devId)
                ->setName($name)
                ->save();
    }

    public function getCurrentDevId()
    {
        $devId = $this->di['session']['devId'];

        if ($devId === null || !isset($this->di['devicesList'][$devId])) {
            $devId = null;

            if (count($this->di['devicesList'])) {
                $devices = array_keys($this->di['devicesList']);
                $devId = $devices[0];
            }

            $this->setCurrentDevId($devId);
        }

        return $devId;
    }

    public function setCurrentDevId($devId)
    {
        $this->di['session']['devId'] = $devId;
        //setcookie('devId', $devId, time() + 3600 * 24, '/', $this->di['config']['cookieDomain']);
    }

    /**
     * 
     * @return \CS\Models\Limitation
     */
    private function getLimitation()
    {
        if ($this->limitation === null) {
            $deviceLimitations = new Limitations($this->getDb());
            $this->limitation = $deviceLimitations->getDeviceLimitation($this->di['devId']);
        }

        return $this->limitation;
    }

    public function isPaid($limitation)
    {
        if ($limitation === Limitations::CALL) {
            return $this->getLimitation()->getCall() > 0;
        } else if ($limitation === Limitations::SMS) {
            return $this->getLimitation()->getSms() > 0;
        }

        return $this->getLimitation()->hasOption($limitation);
    }

    public function iCloudMergeWithLocalInfo($userId, array $iCloudDevices)
    {
        $localDevices = array();

        foreach ($this->getUserDevices($userId, 'icloud') as $dbDevice)
            $localDevices[$dbDevice['unique_id']] = $dbDevice;

        foreach ($iCloudDevices as &$iCloudDev) {
            if (array_key_exists($uniqueID = $iCloudDev['SerialNumber'], $localDevices)) {
                $iCloudDev = array_merge($iCloudDev, $localDevices[$uniqueID]);
                $iCloudDev['added'] = true;
                $iCloudDev['quota_used'] = & $iCloudDev['QuotaUsedMb'];
                if ($iCloudDev['last_backup'] < ($lastBackup = strtotime($iCloudDev['LastModified']))) {
                    $iCloudDev['last_backup'] = $lastBackup;
                }
            } else {
                $iCloudDev['added'] = $iCloudDev['active'] = false;
                $iCloudDev['device_name'] = & $iCloudDev['DeviceName'];
                $iCloudDev['model'] = & $iCloudDev['MarketingName'];
                $iCloudDev['unique_id'] = & $iCloudDev['SerialNumber'];
                $iCloudDev['quota_used'] = & $iCloudDev['QuotaUsedMb'];
                $iCloudDev['os_version'] = & $iCloudDev['IosVersion'];
                $iCloudDev['last_backup'] = strtotime($iCloudDev['LastModified']);
                $iCloudDev['expiration_date'] = null;
            }
        }
        return $iCloudDevices;
    }

    /**
     * 
     * @param type $userId
     * @param \Components\CloudDevice[] $devices
     * @return boolean
     */
    public function updateCloudDevicesList($userId, $devices)
    {
        $actualDevices = [];

        foreach ($this->getUserDevices($userId, 'icloud') as $device) {
            $actualDevices[$device['unique_id']] = $device;
        }

        foreach ($devices as $device) {
            $serialNumber = $device->getSerialNumber();
            if (array_key_exists($serialNumber, $actualDevices)) {
                $device->setDeviceId($actualDevices[$serialNumber]['device_id']);
                $device->setLicenseId($actualDevices[$serialNumber]['license_id']);
                $device->setLicenseName($actualDevices[$serialNumber]['package_name']);
            }
        }

        return $devices;
    }

    public function getUserDevices($userId, $platform = null, $isSubscribed = null)
    {
        if ($platform)
            $platformCondition = "AND d.os = {$this->getDb()->quote($platform)}";
        else
            $platformCondition = '';

        if (!is_null($isSubscribed)) {
            if ($isSubscribed)
                $subscriptionHaving = 'HAVING COUNT(l.id) > 0';
            else
                $subscriptionHaving = 'HAVING COUNT(l.id) = 0';
        } else
            $subscriptionHaving = '';

        $minOnlineTime = time() - Manager::ONLINE_PERIOD;
        $data = $this->getDb()->query("
                    SELECT
                        *,
                        IF(d.last_visit > {$minOnlineTime}, 1, 0) online,
                        if(COUNT(l.id), 1, 0) as active,
                        d.id device_id,
                        d.name device_name,
                        l.id license_id,
                        p.group package_group,
                        
                        p.name package_name,
                        di.id icloud_id,
                        di.last_backup last_backup
                    FROM `devices` d
                    LEFT JOIN `devices_icloud` di ON di.dev_id = d.id
                    LEFT JOIN `licenses` l ON 
                        l.`device_id` = d.`id` AND
                        l.`product_type` = {$this->getDb()->quote(ProductRecord::TYPE_PACKAGE)} AND
                        l.`status` = {$this->getDb()->quote(LicenseRecord::STATUS_ACTIVE)}
                    LEFT JOIN `products` p ON p.`id` = l.`product_id`
                    WHERE
                        d.`user_id` = {$this->getDb()->quote($userId)} AND
                        d.`deleted` = 0
                        {$platformCondition}
                    GROUP BY d.`id`
                    {$subscriptionHaving}
                ")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($data as &$item) {

            if ($item['os'] == DeviceRecord::OS_ANDROID && $item['os_version']) {
                list(, $clearOsVersion) = explode('_', $item['os_version']);
                if ($clearOsVersion)
                    $item['os_version'] = $clearOsVersion;
            }

            if ($item['os'] != DeviceRecord::OS_ICLOUD)
                $item['last_sync'] = $item['last_visit'];
        }
        return $data;
    }

    public function existsOnOtherUsers($userId, $uniqueId)
    {
        $user = $this->getDb()->quote($userId);
        $device = $this->getDb()->quote($uniqueId);

        $count = $this->getDb()->query("SELECT
                        COUNT(*) 
                    FROM `devices` 
                    WHERE
                        unique_id = {$device} AND 
                        user_id != {$user}
                    LIMIT 1")->fetchColumn();

        return $count > 0;
    }

    public function getUsersWithDevice($userId, $uniqueId)
    {
        $user = $this->getDb()->quote($userId);
        $deviceUniqueId = $this->getDb()->quote($uniqueId);
        return $this->getDb()->query("SELECT DISTINCT `user_id` FROM `devices` WHERE `user_id` != {$user} AND `unique_id` = {$deviceUniqueId}")->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getReincubateAccountByEmail($email)
    {
        $account = $this->getDb()->quote($email);

        return $this->getDb()->query("SELECT
                                            * 
                                        FROM `reincubate_account` 
                                        WHERE 
                                            `email` = {$account} 
                                        LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    }

    public function getReincubateAccount($accountId)
    {
        $account = $this->getDb()->quote($accountId);

        return $this->getDb()->query("SELECT
                                            * 
                                        FROM `reincubate_account` 
                                        WHERE 
                                            `account_id` = {$account} 
                                        LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    }

    public function getReincubateDevice($accountId, $deviceId)
    {
        $account = $this->getDb()->quote($accountId);
        $device = $this->getDb()->quote($deviceId);

        return $this->getDb()->query("SELECT
                                            * 
                                        FROM `reincubate_device`
                                        WHERE 
                                            `account_id` = {$account} AND
                                            `device_id` = {$device}
                                        LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    }

    public function createReincubateAccount($accountId, $email, $active = true)
    {
        $account = $this->getDb()->quote($accountId);
        $email = $this->getDb()->quote($email);
        $active = $this->getDb()->quote((int) $active);


        return $this->getDb()->exec("INSERT INTO `reincubate_account` SET
                                        `account_id` = {$account},
                                        `email` = {$email},
                                        `active` = {$active}");
    }

    public function setReincubateAccountActive($accountId, $active = true)
    {
        $account = $this->getDb()->quote($accountId);
        $active = $this->getDb()->quote((int) $active);

        return $this->getDb()->exec("UPDATE `reincubate_account` SET
                                            `active` = {$active}
                                        WHERE
                                            `account_id` = {$account}");
    }
    
    public function createReincubateDevice($accountId, $deviceId, $active = true)
    {
        $account = $this->getDb()->quote($accountId);
        $device = $this->getDb()->quote($deviceId);
        $active = $this->getDb()->quote((int) $active);


        return $this->getDb()->exec("INSERT INTO `reincubate_device` SET
                                        `account_id` = {$account},
                                        `device_id` = {$device},
                                        `active` = {$active}");
    }

    public function setReincubateDeviceActive($accountId, $deviceId, $active = true)
    {
        $account = $this->getDb()->quote($accountId);
        $device = $this->getDb()->quote($deviceId);
        $active = $this->getDb()->quote((int) $active);

        return $this->getDb()->exec("UPDATE `reincubate_device` SET
                                            `active` = {$active}
                                        WHERE
                                            `account_id` = {$account} AND
                                            `device_id` = {$device}
                                            ");
    }

}

class DevicesInvalidNetworkException extends \Exception {
    
}
