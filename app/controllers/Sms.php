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
        
        if (DeviceOptions::isDeletedDataAvailable($this->di['currentDevice']['os'])) {
            $this->setView('cp/sms/indexWithStatuses.htm');
        } else {
            $this->setView('cp/sms/index.htm');
        }
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
        
        $list = $smsModel->getPhoneSmsList($this->di['devId'], $this->params['phoneNumber']);
        $this->view->list = $list;
        $this->view->isGroup = false;

        if (count($list)) {
            $this->view->userName = $list[0]['name'];
            $this->view->userPhone = $this->params['phoneNumber'];
        } else {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('sms'));
        }

        
        $this->view->tab = 'sms';
        $this->view->id = $this->params['phoneNumber'];
        
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
        
        $list = $smsModel->getPhoneGroupSmsList($this->di['devId'], $this->params['group']);
        $this->view->list = $list;
        $this->view->isGroup = true;

        if (count($list)) {
            $this->view->members = $smsModel->getGroupMembers($this->di['devId'], $this->params['group']);
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
    }
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::SMS);
    }

}
