<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
    CS\Devices\Limitations,
    CS\Devices\DeviceOptions;

class Sms extends BaseModuleController {

    protected $module = Modules::SMS;

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        $smsModel = new \Models\Cp\Sms($this->di);
        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $smsModel->getDataTableData(
                $this->di['devId'],
                $dataTableRequest->buildResult(array('timeFrom', 'timeTo'))
            );
            
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        $this->view->hasRecords = $smsModel->hasRecords($this->di['devId']);
        $this->view->limitEnd = !$this->isModulePaid();

        $this->setView('cp/sms/indexWithStatuses.htm');
    }

    public function listAction() {
        $smsModel = new \Models\Cp\Sms($this->di);

        if ($this->getRequest()->isAjax()) {
            $currPage = ($this->getRequest()->hasPost('currPage') && $this->getRequest()->post('currPage') > 0) ? $this->getRequest()->post('currPage') : 0;
            $perPage = ($this->getRequest()->hasPost('perPage') && $this->getRequest()->post('perPage') > 0) ? $this->getRequest()->post('perPage') : $this->lengthPage;
            $search = ($this->getRequest()->hasPost('search')) ? $this->getRequest()->post('search') : false;

            $data = array();
            $data = $smsModel->getDataPhoneSmsList($this->di['devId'], $this->params['phoneNumber'], $search, $currPage, $perPage);

            $this->makeJSONResponse($data);
        }

        $name = $smsModel->getNumberName($this->di['devId'], $this->params['phoneNumber']);
        $this->view->isGroup = false;

        if ($name !== false) {
            $this->view->userName = $name;
            $this->view->userPhone = $this->params['phoneNumber'];
        } else {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('sms'));
        }

        $this->view->tab = 'sms';
        $this->view->id = urlencode($this->params['phoneNumber']);

        $this->setView('cp/sms/list.htm');
    }

    public function groupListAction() {
        $smsModel = new \Models\Cp\Sms($this->di);

        if ($this->getRequest()->isAjax()) {

            $currPage = ($this->getRequest()->hasPost('currPage') && $this->getRequest()->post('currPage') > 0) ? $this->getRequest()->post('currPage') : 0;
            $perPage = ($this->getRequest()->hasPost('perPage') && $this->getRequest()->post('perPage') > 0) ? $this->getRequest()->post('perPage') : $this->lengthPage;
            $search = ($this->getRequest()->hasPost('search')) ? $this->getRequest()->post('search') : false;

            $data = array();
            $data = $smsModel->getDataPhoneGroupSmsList($this->di['devId'], $this->params['group'], $search, $currPage, $perPage);

            $this->makeJSONResponse($data);
        }

        $members = $smsModel->getGroupMembers($this->di['devId'], $this->params['group']);
        $this->view->isGroup = true;

        if (count($members)) {
            $this->view->members = $members;
        } else {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('sms'));
        }

        $this->view->tab = 'sms/group';
        $this->view->id = $this->params['group'];

        $this->setView('cp/sms/list.htm');
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('SMS');

        if($this->di['currentDevice']['os'] != 'icloud'){
            $this->view->customUtcOffset = 0;
        }
        
        $this->view->moduleId = Modules::SMS;
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::SMS);
    }

}
