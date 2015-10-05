<?php

namespace Controllers;

use Models\Modules,
    CS\Devices\Limitations,
    System\FlashMessages;

class Calls extends BaseModuleController
{

    protected $module = Modules::CALLS;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $callsModel = new \Models\Cp\Calls($this->di);
        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $callsModel->getDataTableData(
                    $this->di['devId'], $dataTableRequest->buildResult(array('timeFrom', 'timeTo'))
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        $this->view->hasRecords = $callsModel->hasRecords($this->di['devId']);
        $this->view->blackList = $callsModel->getBlackList($this->di['devId']);
        $this->view->limitEnd = !$this->isModulePaid();
        
        if($this->di['currentDevice']['os'] != 'icloud'){
            $this->view->customUtcOffset = 0;
        }

        $this->setView('cp/calls.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Calls');
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::CALL);
    }

}
