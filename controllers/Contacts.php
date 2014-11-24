<?php

namespace Controllers;

use Models\Modules,
    CS\Devices\Limitations;

class Contacts extends BaseModuleController
{

    protected $module = Modules::CONTACTS;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $contactsModel = new \Models\Cp\Contacts($this->di);
        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $contactsModel->getDataTableData(
                    $this->di['devId'], $dataTableRequest->buildResult(array('deleted'))
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        $this->view->hasRecords = $contactsModel->hasRecords($this->di['devId']);

        $this->setView('cp/contacts.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Contacts');
    }
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::CONTACT);
    }

}
