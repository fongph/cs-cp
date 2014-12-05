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
        if (!isset($this->di['session']['devId']) ||
                !isset($this->di['devicesList'][$this->di['session']['devId']])) {

            $devId = null;

            if (count($this->di['devicesList'])) {
                $devices = array_keys($this->di['devicesList']);
                $devId = $devices[0];
            }

            $this->setCurrentDevId($devId);
            return $devId;
        }

        return $this->di['session']['devId'];
    }

    public function setCurrentDevId($devId)
    {
        $this->di['session']['devId'] = $devId;
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

}

class DevicesInvalidNetworkException extends \Exception
{
    
}
