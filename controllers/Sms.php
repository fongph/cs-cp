<?php

namespace Controllers;

use System\FlashMessages;

class Sms extends BaseController {

    protected $module = 'sms';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        $smsModel = new \Models\Cp\Sms($this->di);
        if ($this->isAjaxRequest()) {
            $dataTableRequest = new \System\DataTableRequest();

            $dataTableRequest->getRequest($_GET, array('timeFrom', 'timeTo'));
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($smsModel->getDataTableData($this->di['devId'], $dataTableRequest->getResult()));
        }

        if ($this->view->paid) {
            $this->view->hasRecords = $smsModel->hasRecords($this->di['devId']);
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

}
