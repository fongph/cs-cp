<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
    CS\Devices\Limitations;

class Vk extends BaseModuleController
{

    protected $module = Modules::VK;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $vkModel = new \Models\Cp\Vk($this->di);

        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            if ($this->params['tab'] === 'private') {
                $data = $vkModel->getPrivateDataTableData(
                        $this->di['devId'], $dataTableRequest->buildResult(array('account', 'timeFrom', 'timeTo'))
                );
            } elseif ($this->params['tab'] === 'group') {
                $data = $vkModel->getGroupDataTableData(
                        $this->di['devId'], $dataTableRequest->buildResult(array('account', 'timeFrom', 'timeTo'))
                );
            }
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        if ($this->view->paid) {
            $this->view->accounts = $vkModel->getAccountsList($this->di['devId']);
        }

        $this->setView('cp/vk.htm');
    }

    public function listAction()
    {
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

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('VK Messages');
    }
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::VK);
    }

}
