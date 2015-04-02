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

            $data = $applicationsModel->getNewDataTableData(
                    $this->di['devId'], $dataTableRequest->buildResult()
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }
        
        $this->view->hasRecords = $applicationsModel->hasRecords($this->di['devId']);
        $this->setView('cp/applications.htm');
    }

    public function manageAction()
    {
        $applicationsModel = new \Models\Cp\Applications($this->di);

        if (($application = $applicationsModel->getApplicationData($this->di['devId'], $this->params['id'])) === false) {
            $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('The application was not found.'));
            $this->redirect($this->di['router']->getRouteUrl('applications'));
        }

        if ($this->getRequest()->hasPost('status', 'hardBlock', 'minutes')) {
            $this->checkDemo($this->di['router']->getRouteUrl('applicationsManage', array(
                'id' => $this->params['id']
            )));
            
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

        /**
         * @deprecated until there are no applications with old version
         */
        if (($this->di['currentDevice']['os'] === 'android' && $this->di['currentDevice']['app_version'] < 5) ||
                ($this->di['currentDevice']['os'] === 'ios' && $this->di['currentDevice']['app_version'] < 3)) {
            $this->view->showUpdateBlock = $this->di['currentDevice']['os'];
        }
        
        $this->view->title = $this->di['t']->_('View Applications');
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::APPLICATIONS);
    }

}
