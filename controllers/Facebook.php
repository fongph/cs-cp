<?php

namespace Controllers;

use System\FlashMessages;

class Facebook extends BaseController {

    protected $module = 'facebook';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        $facebookModel = new \Models\Cp\Facebook($this->di);

        if ($this->isAjaxRequest()) {
            $dataTableRequest = new \System\DataTableRequest();

            $dataTableRequest->getRequest($_GET, array('account', 'timeFrom', 'timeTo'));
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($facebookModel->getDataTableData($this->di['devId'], $dataTableRequest->getResult()));
        }

        if ($this->view->paid) {
            $this->view->accounts = $facebookModel->getAccountsList($this->di['devId']);
        }

        $this->setView('cp/facebook.htm');
    }

    public function listAction() {
        $facebookModel = new \Models\Cp\Facebook($this->di);

        switch ($this->params['tab']) {
            case 'group':
                $this->view->list = $facebookModel->getGroupList($this->di['devId'], $this->params['account'], $this->params['id']);
                $this->view->users = $facebookModel->getGroupUsers($this->di['devId'], $this->params['account'], $this->params['id']);
                break;

            case 'private':
                $this->view->list = $facebookModel->getPrivateList($this->di['devId'], $this->params['account'], $this->params['group']);
                break;
        }

        if (!count($this->view->list)) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('facebook'));
        }
        
        $this->view->tab = $this->params['tab'];

        $this->setView('cp/facebookList.htm');
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Facebook Messages');
    }

}
