<?php

namespace Components;

use Models\Devices as DevicesModel;
use Reincubate\ReincubateClient;
use Reincubate\MasterClient;

/**
 * Description of CloudDeviceManager
 *
 * @author orest
 */
class CloudDeviceManager {

    private $userId;
    private $privateKey = 'asdasdasd';
    private $state;
    private $devicesModel;
    private $reincubateClient;

    public function __construct($userId, DevicesModel $devicesModel, ReincubateClient $reincubateClient)
    {
        $this->userId = $userId;
        $this->devicesModel = $devicesModel;
        $this->reincubateClient = $reincubateClient;
    }

    public function setState(CloudDeviceState $state)
    {
        $this->state = $state;
    }

    /**
     * 
     * @return CloudDeviceState
     */
    public function getState()
    {
        if ($this->state == null) {
            $this->state = new CloudDeviceState();
        }

        return $this->state;
    }

    public function decryptState($string)
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

    public function encryptState(CloudDeviceState $state)
    {
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt(serialize($state), 'aes-128-cbc', $this->privateKey, OPENSSL_RAW_DATA, $iv);
        $hash = hash('md5', $encrypted, true);

        return base64_encode($encrypted . $iv . $hash);
    }

    private function checkCredentials()
    {
        $setup = new \AppleCloud\ServiceClient\Setup();

        try {
            $state = $this->getState();
            $setup->authenticate($state->getAppleId(), $state->getApplePassword());
        } catch (\AppleCloud\ServiceClient\Exception\TwoStepVerificationException $e) {
            // if 2fa exception catched than password is right
        }

        return true;
    }

    public function authenticate()
    {
        //throw new \Reincubate\Exception\Master\TwoFactorAuthenticationRequiredException('1', 95833, []);
        
        //try auth on icloud without reincubate to check credentials
        $this->checkCredentials();

        $state = $this->getState();

        $master = $this->reincubateClient->getMasterClient();

        $account = $this->devicesModel->getReincubateAccountByEmail($state->getAppleId());

        if ($account !== false) {
            $state->setReincubateAccountId($account['account_id']);

            if ($account['active']) {
                return;
            }

            $master->resubscribeAccount($account['account_id'], $state->getApplePassword());
            $this->devicesModel->setReincubateAccountActive($state->getReincubateAccountId());
            return;
        }

        try {
            $accountId = $master->subscribeAccount($state->getAppleId(), $state->getApplePassword());
            $state->setReincubateAccountId($accountId);
        } catch (\Reincubate\Exception\Master\AccountAlreadyActiveException $e) {
            $state->setReincubateAccountId($e->getAccountId());
        }

        $this->devicesModel->createReincubateAccount($state->getReincubateAccountId(), $state->getAppleId());
    }

    /**
     * 
     * @return CloudDevice[]
     */
    public function getDevicesList()
    {
        $master = $this->reincubateClient->getMasterClient();

        $state = $this->getState();

        $list = $master->getDevicesList($state->getReincubateAccountId());

        return $this->prepareDevicesList($list);
    }

    public function activateReincubateDevice()
    {
        $master = $this->reincubateClient->getMasterClient();

        $state = $this->getState();

        $device = $this->devicesModel->getReincubateDevice($state->getReincubateAccountId(), $state->getReincubateDeviceId());
        
        $master->subscribeDevice($state->getReincubateAccountId(), $state->getReincubateDeviceId());
        
        if ($device == false) {
            $this->devicesModel->createReincubateDevice($state->getReincubateAccountId(), $state->getReincubateDeviceId());
        } elseif (!$device['active']) {
            $this->devicesModel->setReincubateDeviceActive($state->getReincubateAccountId(), $state->getReincubateDeviceId());
        }
        
        return true;
    }

    public function performTwoFactorAuth()
    {
        //return true;
        
        $master = $this->reincubateClient->getMasterClient();

        $state = $this->getState();

        return $master->performTwoFactorAuthenticationChallenge($state->getReincubateAccountId(), 0);
    }

    public function submitTwoFactorAuth($code)
    {
        $master = $this->reincubateClient->getMasterClient();

        $state = $this->getState();

        //$accountId = 95833;
        $accountId = $master->submitTwoFactorAuthenticationChallenge($state->getReincubateAccountId(), $code);
        $state->setReincubateAccountId($accountId);

        $account = $this->devicesModel->getReincubateAccount($accountId);
        if ($account != false) {
            $this->devicesModel->setReincubateAccountActive($accountId);
        } else {
            $this->devicesModel->createReincubateAccount($accountId, $state->getAppleId());
        }
    }

    public function add($device, \CS\Models\License\LicenseRecord $licenseRecord, \CS\Models\Device\DeviceRecord $deviceRrecord)
    {
        $deviceRrecord->getICloudDevice();
    }

    /**
     * 
     * @return CloudDevice | boolean
     */
    public function getRequestedDevice()
    {
        $reincubateDeviceId = $this->getState()->getReincubateDeviceId();

        if (!$reincubateDeviceId) {
            return false;
        }

        $devices = $this->getDevicesList();

        if (!isset($devices[$reincubateDeviceId])) {
            return false;
        }

        return $devices[$reincubateDeviceId];
    }

    private static function toTimestamp($date)
    {
        $a = \DateTime::createFromFormat('Y-m-d H:i:s.u', $date, new \DateTimeZone('UTC'));
        return $a->getTimestamp();
    }

    private function prepareDevicesList($list)
    {
        $result = [];

        $state = clone $this->getState();

        foreach ($list as $key => $data) {
            $state = $state->setReincubateDeviceId($key);

            $device = new CloudDevice();
            $device->setReincubateDeviceInfo($data);
            $device->setToken($this->encryptState($state));

            $result[$key] = $device;
        }

        if (!count($result)) {
            return [];
        }

        return $this->devicesModel->updateCloudDevicesList($this->userId, $result);
    }

}
