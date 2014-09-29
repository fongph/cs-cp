<?php

namespace Controllers;

class Bookmarks extends BaseController {

    protected $module = 'bookmarks';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        $bookmarksModel = new \Models\Cp\Bookmarks($this->di);
        if ($this->isAjaxRequest()) {
            $dataTableRequest = new \System\DataTableRequest();

            $dataTableRequest->getRequest($_GET, array('deleted'));
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($bookmarksModel->getDataTableData($this->di['devId'], $dataTableRequest->getResult()));
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

}
