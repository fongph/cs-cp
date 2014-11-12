<?php

namespace Controllers;

use System\FlashMessages;

class Vk extends BaseController {

    protected $module = 'vk';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        $vkModel = new \Models\Cp\Vk($this->di);

        if ($this->isAjaxRequest()) {
            $dataTableRequest = new \System\DataTableRequest();

            $dataTableRequest->getRequest($_GET, array('account', 'timeFrom', 'timeTo'));
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            if ($this->params['tab'] === 'private') {
                $this->makeJSONResponse($vkModel->getPrivateDataTableData($this->di['devId'], $dataTableRequest->getResult()));
            } elseif ($this->params['tab'] === 'group') {
                $this->makeJSONResponse($vkModel->getGroupDataTableData($this->di['devId'], $dataTableRequest->getResult()));
            }
        }

        if ($this->view->paid) {
            $this->view->accounts = $vkModel->getAccountsList($this->di['devId']);
        }

        $this->setView('cp/vk.htm');
    }

    public function listAction() {
        $vkModel = new \Models\Cp\Vk($this->di);

        switch ($this->params['tab']) {
            case 'group':
                $this->view->list = $vkModel->getGroupList($this->di['devId'], $this->params['account'], $this->params['id']);
                $this->view->users = $vkModel->getGroupUsers($this->di['devId'], $this->params['account'], $this->params['id']);
                break;

            case 'private':
                $this->view->list = $vkModel->getPrivateList($this->di['devId'], $this->params['account'], $this->params['id']);
                break;
        }

        if (!count($this->view->list)) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('vk'));
        }

        $this->view->tab = $this->params['tab'];
        $this->setView('cp/vkList.htm');
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('VK Messages');
    }

}
