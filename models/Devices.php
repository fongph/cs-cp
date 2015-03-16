<?php

namespace Models;

use CS\Devices\Manager as DevicesManager,
    CS\Devices\Limitations;
use CS\Devices\Manager;
use CS\Models\Device\DeviceRecord;
use CS\Models\License\LicenseRecord;
use CS\Models\Product\ProductRecord;

class Devices extends \System\Model
{
    
    private $limitation;

    public function delete($devId)
    {
        $devicesManager = new DevicesManager($this->getDb());
        $devicesManager->deleteDevice($devId);
    }

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
        $devId = $this->di->getRequest()->cookie('devId');
        
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
        setcookie('devId', $devId, time() + 3600 * 24, '/', $this->di['config']['cookieDomain']);
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
        
        foreach($this->getUserDevices($userId, 'icloud') as $dbDevice)
            $localDevices[$dbDevice['unique_id']] = $dbDevice;
        
        foreach($iCloudDevices as &$iCloudDev){
            if(array_key_exists($uniqueID = $iCloudDev['SerialNumber'], $localDevices)){
                $iCloudDev = array_merge($iCloudDev, $localDevices[$uniqueID]);
                $iCloudDev['added'] = true;
                $iCloudDev['quota_used'] =& $iCloudDev['QuotaUsedMb'];
                if($iCloudDev['last_backup'] < ($lastBackup = strtotime($iCloudDev['LastModified'])) ){
                    $iCloudDev['last_backup'] = $lastBackup;
                }
            } else {
                $iCloudDev['added'] = $iCloudDev['active'] = false;
                $iCloudDev['device_name'] =& $iCloudDev['DeviceName'];
                $iCloudDev['model'] =& $iCloudDev['MarketingName'];
                $iCloudDev['unique_id'] =& $iCloudDev['SerialNumber'];
                $iCloudDev['quota_used'] =& $iCloudDev['QuotaUsedMb'];
                $iCloudDev['os_version'] =& $iCloudDev['IosVersion'];
                $iCloudDev['last_backup'] = strtotime($iCloudDev['LastModified']);
            }
        }
        return $iCloudDevices;
    }

    public function getUserDevices($userId, $platform = null, $isSubscribed = null)
    {
        if($platform) $platformCondition = "AND d.os = {$this->getDb()->quote($platform)}";
        else $platformCondition = '';

        if(!is_null($isSubscribed)) {
            if($isSubscribed) $subscriptionHaving = 'HAVING COUNT(l.id) > 0';
            else $subscriptionHaving = 'HAVING COUNT(l.id) = 0';
        } else $subscriptionHaving = '';
        
        $minOnlineTime = time() - Manager::ONLINE_PERIOD;
        $data = $this->getDb()->query("
                    SELECT
                        *,
                        IF(d.last_visit > {$minOnlineTime}, 1, 0) online,
                        if(COUNT(l.id), 1, 0) as active,
                        d.id device_id,
                        d.name device_name,
                        p.name package_name,
                        di.id icloud_id,
                        UNIX_TIMESTAMP(di.last_backup) last_backup
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
        foreach($data as &$item) {

            if($item['os'] == DeviceRecord::OS_ANDROID && $item['os_version']){
                list(,$clearOsVersion) = explode('_', $item['os_version']);
                if($clearOsVersion) $item['os_version'] = $clearOsVersion;
            }

            if($item['os'] == 'icloud'){
                $sync = $item['last_backup'];
            } else $sync = $item['last_visit'];

            //todo js Date Format
            if($sync) $item['last_sync'] = date('j M Y g:i A', $sync);
            else $item['last_sync'] = '-';
        }

        return $data;
    }

}

class DevicesInvalidNetworkException extends \Exception
{
    
}
