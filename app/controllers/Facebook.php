<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
    CS\Devices\Limitations;

class Facebook extends BaseModuleController
{

    protected $module = Modules::FACEBOOK;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $facebookModel = new \Models\Cp\Facebook($this->di);

        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $facebookModel->getDataTableData(
                    $this->di['devId'], $dataTableRequest->buildResult(array('account', 'timeFrom', 'timeTo'))
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        if ($this->view->paid) {
            $this->view->accounts = $facebookModel->getAccountsList($this->di['devId']);
        }

        $this->setView('cp/facebook.htm');
    }

    public function listAction()
    {
        $facebookModel = new \Models\Cp\Facebook($this->di);

        switch ($this->params['tab']) {
            case 'group':
                $this->view->list = $facebookModel->getGroupList($this->di['devId'], $this->params['account'], $this->params['id']);
                $this->view->users = $facebookModel->getGroupUsers($this->di['devId'], $this->params['account'], $this->params['id']);
                break;

            case 'private':
                $this->view->list = $facebookModel->getPrivateList($this->di['devId'], $this->params['account'], $this->params['id']);
                break;
        }

        if (!count($this->view->list)) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('facebook'));
        }

        $this->view->tab = $this->params['tab'];

        $this->setView('cp/facebookList.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Facebook Messages');
    }
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::FACEBOOK);
    }

}
