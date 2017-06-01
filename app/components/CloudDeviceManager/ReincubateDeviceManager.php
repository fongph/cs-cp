<?php

namespace Components\CloudDeviceManager;

use Models\Devices as DevicesModel;
use Reincubate\ReincubateClient;
use Cache\Adapter\Common\AbstractCachePool;

/**
 * Description of CloudDeviceManager
 *
 * @author orest
 */
class ReincubateDeviceManager extends AbstractCloudDeviceManager {

    private $devicesModel;
    private $reincubateClient;

    /**
     *
     * @var AbstractCachePool 
     */
    private $cahcePool;

    public function __construct($userId, DevicesModel $devicesModel, ReincubateClient $reincubateClient, AbstractCachePool $cahcePool)
    {
        parent::__construct($userId, $devicesModel, 'asdasdasdasda');
        $this->reincubateClient = $reincubateClient;
        $this->cahcePool = $cahcePool;
    }

    private function checkCredentials()
    {
        $setup = new \AppleCloud\ServiceClient\Setup();

        try {
            $state = $this->getState();
            $setup->authenticate($state->getAppleId(), $state->getApplePassword());
        } catch (\AppleCloud\ServiceClient\Exception\BadCredentialsException $e) {
            throw new Exception\BadCredentialsException("Bad credentials passed", $e);
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
        } catch (\Reincubate\Exception\Master\TwoFactorAuthenticationRequiredException $e) {
            $state->setReincubateAccountId($e->getAccountId());
            throw new Exception\TwoFactorAuthenticationRequiredException("Two factor authentication required");
        }

        $this->devicesModel->createReincubateAccount($state->getReincubateAccountId(), $state->getAppleId());
    }

    /**
     * 
     * @return CloudDevice[]
     */
    public function getDevicesList()
    {
        $state = $this->getState();

        $key = 'devices_list_' . $state->getReincubateAccountId();
        $item = $this->cahcePool->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        $master = $this->reincubateClient->getMasterClient();
        $list = $master->getDevicesList($state->getReincubateAccountId());

        $devicesList = $this->prepareDevicesList($list);

        $item->set($devicesList);
        $item->expiresAfter(3600);
        $this->cahcePool->save($item);

        return $devicesList;
    }

    public function activateDevice()
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
