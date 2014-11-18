<?php

namespace Controllers;

use System\FlashMessages,
    CS\Devices\Manager as DevicesManager;

class Devices extends BaseController
{

    public function addAction()
    {
        $this->view->title = $this->di->getTranslator()->_('New Device');

        $devicesManager = new DevicesManager($this->di['db']);
        $devicesManager->setRedisConfig($this->di['config']['redis']);

        try {
            $this->view->code = $devicesManager->getUserDeviceAddCode($this->auth['id']);
        } catch (CS\Devices\Manager\DeviceCodeGenerationException $e) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, "Error during add device! Pleace try again later!");
            $this->di['logger']->addCritical("Device code generation failed!");
            $this->view->code = false;
        }

        $this->setView('devices/add.htm');
    }

}
