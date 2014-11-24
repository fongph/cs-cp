<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
    CS\Devices\Limitations;

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
            try {
                $browserHistoryModel->addSiteBlock($this->di['devId'], $this->getRequest()->get('block'));
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The domain has been successfully added!'));
            } catch (\Models\Cp\BrowserHistoryInvalidDomainNameException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid domain name!'));
            } catch (\Models\Cp\BrowserHistoryDomainAlreadyExistsException $e) {
                $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('The domain already exists on the list!'));
            }

            $this->redirect($this->di['router']->getRouteUrl('browserHistory') . '#blocked');
        } else if ($this->getRequest()->get('unblock') !== null) {
            try {
                $browserHistoryModel->addSiteUnblock($this->di['devId'], $this->getRequest()->get('unblock'));
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The domain has been successfully unlocked!'));
            } catch (\Models\Cp\BrowserHistoryInvalidDomainNameException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid domain name!'));
            } catch (\Models\Cp\BrowserHistoryDomainAlreadyUnblockedException $e) {
                $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('The domain has already been unlocked!'));
            } catch (\Models\Cp\BrowserHistoryUndefinedException $e) {
                $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('The domain already exists on the list!'));
            }

            $this->redirect($this->di['router']->getRouteUrl('browserHistory') . '#blocked');
        }

        if ($this->view->paid) {
            $this->view->hasRecords = $browserHistoryModel->hasRecords($this->di['devId']);
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

        $this->view->title = $this->di['t']->_('View Browser History');
    }
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::BROWSER_HISTORY);
    }

}
