<?php

namespace Controllers;

use System\FlashMessages,
    CS\Devices\Manager as DevicesManager,
    CS\Users\UsersManager,
    CS\Users\PasswordsNotEqualException,
    CS\Users\PasswordTooShortException,
    CS\Users\InvalidPasswordException;

class Devices extends BaseController
{

    public function addAction()
    {
        $this->view->title = $this->di->getTranslator()->_('New Device');
        
        $devicesManager = new DevicesManager($this->di['db']);
        $devicesManager->setRedisConfig($this->di['config']['redis']);
        
        $this->view->code = $devicesManager->getUserDeviceAddCode($this->auth['id']);
        $this->setView('devices/add.htm');
    }

}
