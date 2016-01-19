<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
    CS\Devices\Limitations;

class Snapchat extends BaseModuleController
{

    protected $module = Modules::SNAPCHAT;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $snapchatModel = new \Models\Cp\Snapchat($this->di);

        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $snapchatModel->getDataTableData(
                    $this->di['devId'], $dataTableRequest->buildResult(array('account', 'timeFrom', 'timeTo'))
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        if ($this->view->paid) {
            $this->view->accounts = $snapchatModel->getAccountsList($this->di['devId']);
        }

        $this->setView('cp/snapchat/index.htm');
    }

    public function listAction()
    {
        $snapchatModel = new \Models\Cp\Snapchat($this->di);

        if ($this->getRequest()->isAjax()) {
            $currPage = ($this->getRequest()->hasPost('currPage') && $this->getRequest()->post('currPage') > 0) ? $this->getRequest()->post('currPage') : 0;
            $perPage = ($this->getRequest()->hasPost('perPage') && $this->getRequest()->post('perPage') > 0) ? $this->getRequest()->post('perPage') : $this->lengthPage;
            $search = ($this->getRequest()->hasPost('search')) ? $this->getRequest()->post('search') : false;

            $data = $snapchatModel->getItemsList($this->di['devId'], $this->params['account'], $this->params['id'], $search, $currPage, $perPage);            
            $this->makeJSONResponse($data);
        }

        if ($snapchatModel->dialogNotExists($this->di['devId'], $this->params['account'], $this->params['id'])) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('snapchat'));
        }

        $this->view->user = $snapchatModel->getUserName($this->di['devId'], $this->params['account'], $this->params['id']);
        
        $this->view->account = $this->params['account'];
        $this->view->id = urlencode($this->params['id']);

        $this->setView('cp/snapchat/list.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Snapchat');
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::SNAPCHAT);
    }

}
