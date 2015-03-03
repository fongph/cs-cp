<?php

namespace Models;

use CS\Devices\Manager as DevicesManager,
    CS\Devices\Limitations;

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
        if(empty($iCloudDevices)) return $iCloudDevices;

        $devicesManager = new DevicesManager($this->getDb());
        $localDevices = array();
        foreach($devicesManager->getUserActiveDevices($userId) as $dbDevice){
            $localDevices[$dbDevice['unique_id']] = array(
                'active' => $dbDevice['active']
            );
        }
        foreach($iCloudDevices as &$iCloudDev){
            if(array_key_exists($iCloudDev['SerialNumber'], $localDevices)){

                $iCloudDev['added'] = true;
                $iCloudDev['active'] = $localDevices[$iCloudDev['SerialNumber']]['active'];

            } else $iCloudDev['added'] = $iCloudDev['active'] = false;
        }
        return $iCloudDevices;
    }

}

class DevicesInvalidNetworkException extends \Exception
{
    
}
