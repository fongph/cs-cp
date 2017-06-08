<?php

namespace Components\CloudDeviceManager;

use GuzzleHttp\Client;
use Models\Devices as DevicesModel;
use GuzzleHttp\Exception\ClientException;
use Components\CloudDevice\AppleCloudDevice;

/**
 * Description of AppleCloudDeviceManager
 *
 * @author orest
 */
class AppleCloudDeviceManager extends AbstractCloudDeviceManager {

    private $client;
    private $token;
    private $devicesList;

    public function __construct($baseUrl, $token, $userId, DevicesModel $devicesModel, $privateKey)
    {
        $this->client = new Client(['base_url' => $baseUrl]);
        $this->token = $token;

        parent::__construct($userId, $devicesModel, $privateKey);
    }

    public function activateDevice()
    {
        return;
    }

    public function authenticate()
    {
        try {
            $response = $this->client->post('/v1/account/auth', [
                'json' => [
                    'login' => $this->getState()->getAppleId(),
                    'password' => $this->getState()->getApplePassword()
                ],
                'headers' => [
                    'Authenticate' => $this->token
                ]
            ]);
        } catch (ClientException $exception) {
            $this->processClientException($exception);
        }

        $data = json_decode($response->getBody()->getContents(), true);

        if (!isset($data['accountId'])) {
            throw new Exception\CloudDeviceException("Unexpected reponse", $e);
        }

        $this->getState()->setAccountId($data['accountId']);
    }

    public function getDevicesList()
    {
        if ($this->devicesList !== null) {
            return $this->devicesList;
        }

        $accountId = $this->getState()->getAccountId();

        try {
            $response = $this->client->get('/v1/account/' . $accountId . '/backups', [
                'headers' => [
                    'Authenticate' => $this->token
                ]
            ]);
        } catch (ClientException $exception) {
            $this->processClientException($exception);
        }

        $list = json_decode($response->getBody()->getContents(), true);

        $this->devicesList = $this->prepareDevicesList($list);
        return $this->devicesList;
    }

    public function performTwoFactorAuth()
    {
        return;
    }

    /**
     * 
     * @return AppleCloudDevice
     */
    public function getRequestedDevice()
    {
        $deviceId = $this->getState()->getDeviceId();

        if (!$deviceId) {
            return false;
        }

        $devices = $this->getDevicesList();

        if (!isset($devices[$deviceId])) {
            return false;
        }

        return $devices[$deviceId];
    }

    public function submitTwoFactorAuth($code)
    {
        try {
            $response = $this->client->post('/v1/account/auth', [
                'json' => [
                    'login' => $this->getState()->getAppleId(),
                    'password' => $this->getState()->getApplePassword(),
                    'code' => $code
                ],
                'headers' => [
                    'Authenticate' => $this->token
                ]
            ]);
        } catch (ClientException $exception) {
            $this->processClientException($exception);
        }

        $data = json_decode($response->getBody()->getContents(), true);

        if (!isset($data['accountId'])) {
            throw new Exception\CloudDeviceException("Unexpected reponse", $exception);
        }

        $this->getState()->setAccountId($data['accountId']);
    }

    private function processClientException(ClientException $exception)
    {
        $response = $exception->getResponse();

        $data = json_decode($response->getBody()->getContents(), true);
        if (!isset($data['error'])) {
            throw new Exception\CloudDeviceException("Unexpected reponse", $exception);
        }

        switch ($data['error']) {
            case 'invalid-credentials':
                throw new Exception\BadCredentialsException("Bad credentials passed", $exception);
            case 'invalid-verification-code':
                throw new Exception\InvalidVerificationCodeException("Invalid verification code passed", $exception);
            case 'verification-code-required':
                throw new Exception\TwoFactorAuthenticationRequiredException("Verification code required", $exception);
            case 'account-locked':
                throw new Exception\AccountLockedException("Account has been locked", $exception);
            default:
                throw $exception;
        }
    }

    private function prepareDevicesList($list)
    {
        $result = [];

        $state = clone $this->getState();

        foreach ($list as $key => $data) {
            $state->setDeviceId($key);

            $device = new AppleCloudDevice($data);
            $device->setToken($this->encryptState($state));

            $result[$key] = $device;
        }

        if (!count($result)) {
            return [];
        }

        return $this->getDevicesModel()->updateCloudDevicesList($this->getUserId(), $result);
    }

    public function createDeviceCloudRecord()
    {
        $device = $this->getRequestedDevice();

        $db = $this->getDevicesModel()->getDb();
        if ($device->isAvailable()) {
            $deviceCloudRecord = new \CS\Models\Device\DeviceICloudRecord($db);
            $deviceCloudRecord->loadByDevId($device->getDeviceId());

            return $deviceCloudRecord;
        }

        $deviceRecord = new \CS\Models\Device\DeviceRecord($db);
        $deviceRecord->setUserId($this->getUserId())
                ->setUniqueId($device->getSerialNumber())
                ->setName(\CS\Devices\Manager::remove4BytesCharacters($device->getName()))
                ->setOS(\CS\Models\Device\DeviceRecord::OS_ICLOUD)
                ->save();

        $state = $this->getState();

        $deviceCloudRecord = new \CS\Models\Device\DeviceICloudRecord($db);
        $deviceCloudRecord->setDevId($deviceRecord->getId())
                ->setAppleId($state->getAppleId())
                ->setApplePassword($state->getApplePassword())
                ->setDeviceHash($device->getUniqueId())
                ->setLastBackup(0)
                ->setTwoFactorAuthenticationEnabled($state->getTwoFactorAuthEnabled())
                ->save();

        return $deviceCloudRecord;
    }

}
