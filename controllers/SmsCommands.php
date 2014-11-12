<?php

namespace Controllers;

class SmsCommands extends BaseController {

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

}
