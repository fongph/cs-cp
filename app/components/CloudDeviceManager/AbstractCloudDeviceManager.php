<?php

namespace Components\CloudDeviceManager;

use Models\Devices as DevicesModel;
use Components\CloudDeviceState;
use CS\Models\Device\DeviceICloudRecord;

/**
 * Description of AbstractCloudDeviceManager
 *
 * @author orest
 */
abstract class AbstractCloudDeviceManager {

    private $userId;
    private $privateKey;
    private $state;

    /**
     *
     * @var DevicesModel
     */
    private $devicesModel;

    public function __construct($userId, DevicesModel $devicesModel, $privateKey)
    {
        $this->userId = $userId;
        $this->devicesModel = $devicesModel;
        $this->privateKey = $privateKey;
    }

    public final function setState(CloudDeviceState $state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * 
     * @return CloudDeviceState
     */
    public final function getState()
    {
        if ($this->state == null) {
            $this->state = new CloudDeviceState();
        }

        return $this->state;
    }

    public final function decryptState($string)
    {
        $data = base64_decode($string);

        $hash = hash('md5', substr($data, 0, -32), true);
        if ($hash != substr($data, -16)) {
            throw new \Exception("State decrypt failed");
        }

        $iv = substr($data, -32, 16);
        $decryptedData = openssl_decrypt(substr($data, 0, -32), 'aes-128-cbc', $this->privateKey, OPENSSL_RAW_DATA, $iv);

        return unserialize($decryptedData);
    }

    public final function encryptState(CloudDeviceState $state)
    {
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt(serialize($state), 'aes-128-cbc', $this->privateKey, OPENSSL_RAW_DATA, $iv);
        $hash = hash('md5', $encrypted, true);

        return base64_encode($encrypted . $iv . $hash);
    }

    protected final function getUserId()
    {
        return $this->userId;
    }

    /**
     * 
     * @return DevicesModel
     */
    protected final function getDevicesModel()
    {
        return $this->devicesModel;
    }

    public abstract function authenticate();

    public abstract function performTwoFactorAuth();

    public abstract function submitTwoFactorAuth($code);

    public abstract function getDevicesList();

    public abstract function activateDevice();

    public abstract function getRequestedDevice();

    /**
     * @return DeviceICloudRecord
     */
    public abstract function createDeviceCloudRecord();
}
