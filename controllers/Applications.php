<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
    CS\Devices\Limitations;

class Applications extends BaseModuleController
{

    protected $module = Modules::APPLICATIONS;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $applicationsModel = new \Models\Cp\Applications($this->di);
        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $applicationsModel->getDataTableData(
                    $this->di['devId'], $dataTableRequest->buildResult()
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        } else if ($this->getRequest()->get('block') !== null) {
            try {
                $applicationsModel->setBlock($this->di['devId'], $this->getRequest()->get('block'));
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The application has been successfully blocked!'));
            } catch (\Models\Cp\ApplicationsAlreadyBlockedException $e) {
                $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('The application has already been blocked!'));
            } catch (\Models\Cp\ApplicationsNotFoundException $e) {
                $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('The application has not been found!'));
            }

            $this->redirect($this->di['router']->getRouteUrl('applications'));
        } else if ($this->getRequest()->get('unblock') !== null) {
            try {
                $applicationsModel->setUnblock($this->di['devId'], $this->getRequest()->get('unblock'));
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The application has been successfully unblocked!'));
            } catch (\Models\Cp\ApplicationsAlreadyUnblockedException $e) {
                $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('The application has already been unblocked!'));
            } catch (\Models\Cp\ApplicationsNotFoundException $e) {
                $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('The application has not been found!'));
            }

            $this->redirect($this->di['router']->getRouteUrl('applications'));
        }

        $this->view->hasRecords = $applicationsModel->hasRecords($this->di['devId']);
        $this->setView('cp/applications.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Applications');
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::APPLICATIONS);
    }

}
