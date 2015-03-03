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

    private function privateRouter()
    {
        switch ($this->getDI()->getRouter()->getRouteName()) {
            case 'applicationsManage':
                $this->manage();
                break;

            default:
                $this->index();
                break;
        }
    }

    private function index()
    {
        $applicationsModel = new \Models\Cp\Applications($this->di);
        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $applicationsModel->getNewDataTableData(
                    $this->di['devId'], $dataTableRequest->buildResult()
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }
        
        $this->view->hasRecords = $applicationsModel->hasRecords($this->di['devId']);
        $this->setView('cp/applicationsNew.htm');
    }

    /**
     * 
     * @TODO remove old functional on new version
     * @return type
     */
    public function indexAction()
    {
        if (1 && $this->di['isTestUser']($this->auth['id'])) {
            return $this->privateRouter();
        }

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

    private function manage()
    {
        $applicationsModel = new \Models\Cp\Applications($this->di);

        if (($application = $applicationsModel->getApplicationData($this->di['devId'], $this->params['id'])) === false) {
            $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('The application was not found.'));
            $this->redirect($this->di['router']->getRouteUrl('applications'));
        }

        if ($this->getRequest()->hasPost('status', 'hardBlock', 'minutes')) {
            $status = $this->getRequest()->post('status');
            $hardBlock = $this->getRequest()->post('hardBlock');
            $seconds = $this->getRequest()->post('minutes') * 60;

            try {
                $applicationsModel->setApplicationLimits($this->di['devId'], $this->params['id'], $status, $hardBlock, $seconds);
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The application limits has been successfully updated.'));
            } catch (\Models\Cp\ApplicationsInvalidStatusException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid request.'));
            }
            $this->redirect($this->di['router']->getRouteUrl('applications'));
        }

        $this->view->application = $application;
        $this->setView('cp/applicationsManage.htm');
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
