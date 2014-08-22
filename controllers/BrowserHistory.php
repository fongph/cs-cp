<?php

namespace Controllers;

use System\FlashMessages;

class BrowserHistory extends BaseController {

    protected $module = 'browserHistory';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        $browserHistoryModel = new \Models\Cp\BrowserHistory($this->di);
        if ($this->isAjaxRequest()) {
            $dataTableRequest = new \System\DataTableRequest();
            $browserHistoryModel = new \Models\Cp\BrowserHistory($this->di);

            $dataTableRequest->getRequest($_GET, array('timeFrom', 'timeTo'));
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($browserHistoryModel->getDataTableData($this->di['devId'], $dataTableRequest->getResult()));
        } else if (isset($_GET['block'])) {
            try {
                $browserHistoryModel->addSiteBlock($this->di['devId'], $_GET['block']);
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The domain has been successfully added!'));
            } catch (\Models\Cp\BrowserHistoryInvalidDomainNameException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid domain name!'));
            } catch (\Models\Cp\BrowserHistoryDomainAlreadyExistsException $e) {
                $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('The domain already exists on the list!'));
            }

            $this->redirect($this->di['router']->getRouteUrl('browserHistory') . '#blocked');
        } else if (isset($_GET['unblock'])) {
            try {
                $browserHistoryModel->addSiteUnblock($this->di['devId'], $_GET['unblock']);
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

    public function browserBlockedAction() {
        if ($this->isAjaxRequest()) {
            $dataTableRequest = new \System\DataTableRequest();
            $browserHistoryModel = new \Models\Cp\BrowserHistory($this->di);

            $dataTableRequest->getRequest($_GET);
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($browserHistoryModel->getLockedDataTableData($this->di['devId'], $dataTableRequest->getResult()));
        } else {
            $this->error404();
        }
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Browser History');
    }

}
