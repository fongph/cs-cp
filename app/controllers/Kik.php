<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
    CS\Devices\Limitations;

class Kik extends BaseModuleController
{

    protected $module = Modules::KIK;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $kikModel = new \Models\Cp\Kik($this->di);

        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $kikModel->getDataTableData(
                    $this->di['devId'], $dataTableRequest->buildResult(array('account', 'timeFrom', 'timeTo'))
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        if ($this->view->paid) {
            $this->view->accounts = $kikModel->getAccountsList($this->di['devId']);
        }

        $this->setView('cp/kik/index.htm');
    }

    public function listAction()
    {
        $kikModel = new \Models\Cp\Kik($this->di);

        $this->view->list = $kikModel->getMessagesList($this->di['devId'], $this->params['account'], $this->params['id']);
        
        if (!count($this->view->list)) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('kik'));
        }
        
        if ($this->params['tab'] == 'group') {
            $this->view->users = $kikModel->getGroupUsers($this->di['devId'], $this->params['account'], $this->params['id']);
        } else {
            $this->view->user = $kikModel->getUserName($this->di['devId'], $this->params['account'], $this->params['id']);
        }

        $this->view->tab = $this->params['tab'];
        $this->view->account = $this->params['account'];

        $this->setView('cp/kik/list.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Kik');
    }
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::KIK);
    }

}
