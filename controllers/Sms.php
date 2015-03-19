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
                    $dataTableRequest->buildResult(array('timeFrom', 'timeTo', 'deleted'))
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        $this->view->hasRecords = $smsModel->hasRecords($this->di['devId']);
        $this->view->isDeletedAvailable = DeviceOptions::isDeletedDataAvailable($this->di['currentDevice']['os']);
        if($this->di['currentDevice']['os'] != 'icloud'){
            $this->view->customUtcOffset = 0;
        }

        $this->setView('cp/sms.htm');
    }

    public function listAction() {
        $smsModel = new \Models\Cp\Sms($this->di);
        $list = $smsModel->getPhoneSmsList($this->di['devId'], $this->params['phoneNumber']);
        $this->view->list = $list;

        if (count($list)) {
            $this->view->userName = $list[0]['name'];
            $this->view->userPhone = $this->params['phoneNumber'];
        } else {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('sms'));
        }

        $this->setView('cp/smsList.htm');
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View SMS');
    }
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::SMS);
    }

}
