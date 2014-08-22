<?php

namespace Controllers;

class Contacts extends BaseController {

    protected $module = 'contacts';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        $contactsModel = new \Models\Cp\Contacts($this->di);
        if ($this->isAjaxRequest()) {
            $dataTableRequest = new \System\DataTableRequest();

            $dataTableRequest->getRequest($_GET, array('deleted'));
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($contactsModel->getDataTableData($this->di['devId'], $dataTableRequest->getResult()));
        }

        if ($this->view->paid) {
            $this->view->hasRecords = $contactsModel->hasRecords($this->di['devId']);
        }

        $this->setView('cp/contacts.htm');
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Contacts');
    }

}
