<?php

namespace Components;

/**
 * Description of CloudDeviceState
 *
 * @author orest
 */
class CloudDeviceState {

    const ACTION_AUTHENTICATE = 'authenticate';
    const ACTION_ADD_DEVICE = 'add-device';
    const ACTION_SUBMIT_TWO_FACTOR_AUTH_CHALLENGE = 'submit-2fa-challenge';

    private $action;
    private $appleId;
    private $applePassword;
    private $reincubateAccountId;
    private $reincubateDeviceId;
    private $twoFactorAuthEnabled;

    public function getAction()
    {
        return $this->action;
    }

    public function setAction($value)
    {
        $this->action = $value;

        return $this;
    }

    public function getAppleId()
    {
        return $this->appleId;
    }

    public function setAppleId($value)
    {
        $this->appleId = $value;

        return $this;
    }

    public function getApplePassword()
    {
        return $this->applePassword;
    }

    public function setApplePassword($value)
    {
        $this->applePassword = $value;

        return $this;
    }

    public function getReincubateAccountId()
    {
        return $this->reincubateAccountId;
    }

    public function setReincubateAccountId($value)
    {
        $this->reincubateAccountId = $value;

        return $this;
    }

    public function getReincubateDeviceId()
    {
        return $this->reincubateDeviceId;
    }

    public function setReincubateDeviceId($value)
    {
        $this->reincubateDeviceId = $value;

        return $this;
    }
    
    public function getTwoFactorAuthEnabled()
    {
        return $this->twoFactorAuthEnabled;
    }

    public function setTwoFactorAuthEnabled($value)
    {
        $this->twoFactorAuthEnabled = $value;

        return $this;
    }

}
