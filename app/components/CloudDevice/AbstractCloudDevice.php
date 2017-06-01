<?php

namespace Components\CloudDevice;

/**
 * Description of AbstractCloudDevice
 *
 * @author orest
 */
class AbstractCloudDevice {

    protected $id;
    protected $licenseId;
    protected $color;
    protected $backupSize;
    protected $licenseName;
    protected $uniqueId;
    protected $name;
    protected $serialNumber;
    protected $model;
    protected $modelName;
    protected $image;
    protected $lastBackupTimestamp;
    protected $token;

    public function getUniqueId()
    {
        return $this->uniqueId;
    }

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

    public function getLicenseId()
    {
        return $this->licenseId;
    }

    public function setLicenseName($name)
    {
        $this->licenseName = $name;
    }

    public function getLicenseName()
    {
        return $this->licenseName;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getSerialNumber()
    {
        return $this->serialNumber;
    }

    public function getModelName()
    {
        return $this->modelName;
    }

    public function getOsVersion()
    {
        return $this->osVersion;
    }

    public function isAvailable()
    {
        return $this->id > 0;
    }

    public function isActive()
    {
        return $this->licenseId > 0;
    }

    public function getLastBackupTimestamp()
    {
        return $this->lastBackupTimestamp;
    }

    public function getBackupSize()
    {
        return $this->backupSize;
    }

    public function getImage()
    {
        return $this->image; 
    }

    public function setToken($value)
    {
        $this->token = $value;
    }

    public function getToken()
    {
        return $this->token;
    }

}
