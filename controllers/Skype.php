<?php

namespace Controllers;

class Skype extends BaseController {

    protected $module = 'skype';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        $skypeModel = new \Models\Cp\Skype($this->di);

        if ($this->isAjaxRequest()) {
            $dataTableRequest = new \System\DataTableRequest();

            $dataTableRequest->getRequest($_GET, array('account', 'timeFrom', 'timeTo'));
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            switch ($this->params['tab']) {
                case 'messages':
                    $this->makeJSONResponse($skypeModel->getMessagesDataTableData($this->di['devId'], $dataTableRequest->getResult()));
                    break;
                case 'calls':
                    $this->makeJSONResponse($skypeModel->getCallsDataTableData($this->di['devId'], $dataTableRequest->getResult()));
                    break;
            }
        }

        if ($this->view->paid) {
            $this->view->accounts = $skypeModel->getAccountsList($this->di['devId']);
        }

        $this->setView('cp/skype.htm');
    }

    public function listAction() {
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

    public function conferenceAction() {
        $skypeModel = new \Models\Cp\Skype($this->di);
        
        $this->view->users = $skypeModel->getConferenceUsers($this->di['devId'], $this->params['account'], $this->params['id']);
        
        if (!count($this->view->users)) {
            $this->error404();
        }
        
        $this->setView('cp/skypeConference.htm');
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Skype Tracking');
    }

}
