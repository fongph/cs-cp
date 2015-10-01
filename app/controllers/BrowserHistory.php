<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
    CS\Devices\Limitations,
    CS\Devices\DeviceOptions;

class BrowserHistory extends BaseModuleController
{

    protected $module = Modules::BROWSER_HISTORY;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $browserHistoryModel = new \Models\Cp\BrowserHistory($this->di);
        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $browserHistoryModel->getDataTableData(
                    $this->di['devId'], $dataTableRequest->buildResult(array('timeFrom', 'timeTo'))
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        } else if ($this->getRequest()->get('block') !== null) {
            $this->blockDomain($this->getRequest()->get('block'), $browserHistoryModel);
        } else if ($this->getRequest()->get('unblock') !== null) {
            $this->unblockDomain($this->getRequest()->get('unblock'), $browserHistoryModel);
        }

        if ($this->view->paid) {
            $this->view->hasRecords = $browserHistoryModel->hasRecords($this->di['devId']);
        }
        $this->view->isDeviceBlockSiteAvailable = DeviceOptions::isDeviceBlockSiteAvailable($this->di['currentDevice']['os']);

        if($this->di['currentDevice']['os'] != 'icloud'){
            $this->view->customUtcOffset = 0;
        }
        
        $this->setView('cp/browserHistory.htm');
    }

    public function browserBlockedAction()
    {
        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);
            $browserHistoryModel = new \Models\Cp\BrowserHistory($this->di);

            $data = $browserHistoryModel->getLockedDataTableData(
                    $this->di['devId'], $dataTableRequest->buildResult()
            );
            
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        } else {
            $this->error404();
        }
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Browser History');
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::BROWSER_HISTORY);
    }

    private function blockDomain($domain, $browserHistoryModel)
    {
        $this->checkDemo($this->di['router']->getRouteUrl('browserHistory') . '#blocked');
        
        try {
            $browserHistoryModel->addSiteBlock($this->di['devId'], $domain);
            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The domain has been successfully added!'));
        } catch (\Models\Cp\BrowserHistory\InvalidDomainNameException $e) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid domain name!'));
        } catch (\Models\Cp\BrowserHistory\DomainAlreadyExistsException $e) {
            $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('The domain already exists on the list!'));
        }

        $this->redirect($this->di['router']->getRouteUrl('browserHistory') . '#blocked');
    }

    private function unblockDomain($domain, $browserHistoryModel)
    {
        $this->checkDemo($this->di['router']->getRouteUrl('browserHistory') . '#blocked');
        
        try {
            $browserHistoryModel->addSiteUnblock($this->di['devId'], $domain);
            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The domain has been successfully unlocked!'));
        } catch (\Models\Cp\BrowserHistory\InvalidDomainNameException $e) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid domain name!'));
        } catch (\Models\Cp\BrowserHistory\DomainAlreadyUnblockedException $e) {
            $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('The domain has already been unlocked!'));
        } catch (\Models\Cp\BrowserHistory\UndefinedException $e) {
            $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('The domain already exists on the list!'));
        }

        $this->redirect($this->di['router']->getRouteUrl('browserHistory') . '#blocked');
    }

}
