<?php

namespace Controllers;

use Models\Modules,
    CS\Devices\Limitations;

class Bookmarks extends BaseModuleController {

    protected $module = Modules::BROWSER_BOOKMARKS;

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        $bookmarksModel = new \Models\Cp\Bookmarks($this->di);
        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $bookmarksModel->getDataTableData(
                    $this->di['devId'], 
                    $dataTableRequest->buildResult(array('deleted'))
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        if ($this->view->paid) {
            $this->view->hasRecords = $bookmarksModel->hasRecords($this->di['devId']);
        }
        
        $this->setView('cp/bookmarks.htm');
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Bookmarks');
    }
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::BROWSER_BOOKMARK);
    }

}
