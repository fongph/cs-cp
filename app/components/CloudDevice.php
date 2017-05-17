<?php

namespace Components;

use PDO;

/**
 * Description of CloudDevice
 *
 * @author orest
 */
class CloudDevice {

    private $id;
    private $licenseId;
    private $licenseName;
    private $uniqueId;
    private $os;
    private $osVersion;
    private $color;
    private $serialNumber;
    private $name;
    private $model;
    private $modelName;
    private $lastBackupTimestamp;
    private $reincubateDeviceId;
    private $token;

    public function setDeviceId($id)
    {
        $this->id = $id;
    }

    public function getDeviceId()
    {
        return $this->id;
    }

    public function setLicenseId($id)
    {
        $this->licenseId = $id;
    }

    public function setLicenseName($name)
    {
        $this->licenseName = $name;
    }

    public function getLicenseName()
    {
        return $this->licenseName;
    }

    public function setReincubateDeviceInfo($data)
    {
        $this->uniqueId = $data['device_tag'];
        $this->color = $data['colour'];
        $this->os = 'ios';
        $this->osVersion = $data['ios_version'];
        $this->name = $data['device_name'];
        $this->lastBackupTimestamp = self::toTimestamp($data['latest-backup']);
        $this->model = $data['model'];
        $this->modelName = $data['name'];
        $this->serialNumber = $data['serial'];
        $this->reincubateDeviceId = $data['device_id'];
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function getUniqueId()
    {
        return $this->uniqueId;
    }

    public function getSerialNumber()
    {
        return $this->serialNumber;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getModelName()
    {
        return $this->modelName;
    }

    public function getOsVersion()
    {
        return $this->osVersion;
    }

    public function getLastBackupTimestamp()
    {
        return $this->lastBackupTimestamp;
    }

    public function getReincubateDeviceId()
    {
        return $this->reincubateDeviceId;
    }

    public function isAvailable()
    {
        return $this->id > 0;
    }

    public function isActive()
    {
        return $this->licenseId > 0;
    }

    public function getToken()
    {
        return $this->token;
    }

    private static function toTimestamp($date)
    {
        $a = \DateTime::createFromFormat('Y-m-d H:i:s.u', $date, new \DateTimeZone('UTC'));
        return $a->getTimestamp();
    }

}
