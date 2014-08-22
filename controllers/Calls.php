<?php

namespace Controllers;

class Calls extends BaseController {

    protected $module = 'calls';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        $callsModel = new \Models\Cp\Calls($this->di);
        if ($this->isAjaxRequest()) {
            $dataTableRequest = new \System\DataTableRequest();

            $dataTableRequest->getRequest($_GET, array('timeFrom', 'timeTo'));
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($callsModel->getDataTableData($this->di['devId'], $dataTableRequest->getResult()));
        }

        if ($this->view->paid) {
            $this->view->hasRecords = $callsModel->hasRecords($this->di['devId']);
            if ($this->view->hasRecords) {
                $this->view->blackList = $callsModel->getBlackList($this->di['devId']);
            }            
        }
        
        $this->setView('cp/calls.htm');
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Calls');
    }

}
