<?php

namespace Controllers;

use Models\Modules,
    CS\Devices\Limitations;

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
                            $this->di['devId'], 
                            $dataTableRequest->buildResult(array('account', 'timeFrom', 'timeTo'))
                    );
                    break;
                case 'calls':
                    $data = $skypeModel->getCallsDataTableData(
                            $this->di['devId'], 
                            $dataTableRequest->buildResult(array('account', 'timeFrom', 'timeTo'))
                    );
                    break;
            }
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        if ($this->view->paid) {
            $this->view->accounts = $skypeModel->getAccountsList($this->di['devId']);
        }

        $this->setView('cp/skype.htm');
    }

    public function listAction()
    {
        $skypeModel = new \Models\Cp\Skype($this->di);

        switch ($this->params['tab']) {
            case 'group':
                $this->view->list = $skypeModel->getGroupList($this->di['devId'], $this->params['account'], $this->params['id']);
                $this->view->users = $skypeModel->getGroupUsers($this->di['devId'], $this->params['account'], $this->params['id']);
                break;

            case 'private':
                $this->view->list = $skypeModel->getPrivateList($this->di['devId'], $this->params['account'], $this->params['id']);
                break;

            default:
                $this->redirect($this->di['router']->getRouteUrl('skype'));
                break;
        }

        if (!count($this->view->list)) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('skype'));
        }

        $this->view->tab = $this->params['tab'];

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

        $this->view->title = $this->di['t']->_('Skype Tracking');
        
        if($this->di['currentDevice']['os'] != 'icloud'){
            $this->view->customTimezoneOffset = 0;
        }
    }
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::SKYPE);
    }

}
