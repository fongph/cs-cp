<?php

namespace Controllers;

use System\FlashMessages;

class Applications extends BaseController {

    protected $module = 'applications';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        $applicationsModel = new \Models\Cp\Applications($this->di);
        if ($this->isAjaxRequest()) {
            $dataTableRequest = new \System\DataTableRequest();

            $dataTableRequest->getRequest($_GET);
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($applicationsModel->getDataTableData($this->di['devId'], $dataTableRequest->getResult()));
        } else if (isset($_GET['block'])) {
            try {
                $applicationsModel->setBlock($this->di['devId'], $_GET['block']);
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The application has been successfully blocked!'));
            } catch (\Models\Cp\ApplicationsAlreadyBlockedException $e) {
                $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('The application has already been blocked!'));
            } catch (\Models\Cp\ApplicationsNotFoundException $e) {
                $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('The application has not been found!'));
            }

            $this->redirect($this->di['router']->getRouteUrl('applications'));
        } else if (isset($_GET['unblock'])) {
            try {
                $applicationsModel->setUnblock($this->di['devId'], $_GET['unblock']);
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

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Applications');
    }
}
