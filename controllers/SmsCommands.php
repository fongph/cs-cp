<?php

namespace Controllers;

use CS\Devices\Limitations;

class SmsCommands extends BaseModuleController {

    protected $module = 'smsCommands';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        $this->setView('cp/smsCommands.htm');
    }
    
    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('SMS Commands');
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::SMS_COMMANDS);
    }
    
}
