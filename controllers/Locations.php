<?php

namespace Controllers;

class Locations extends BaseController {

    protected $module = 'locations';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        if ($this->isAjaxRequest()) {
            if (!isset($_POST['date'])) {
                return $this->makeJSONResponse(array('success' => 0));
            }

            $locationsModel = new \Models\Cp\Locations($this->di);
            if (($data = $locationsModel->getPoints($this->di['devId'], $_POST['date'])) !== false) {
                return $this->makeJSONResponse(array(
                            'success' => 1,
                            'result' => $data
                ));
            } else {
                return $this->makeJSONResponse(array('success' => 0));
            }

            $dataTableRequest = new \System\DataTableRequest();
            $this->makeJSONResponse($locationsModel->getDataTableData($this->di['devId'], $dataTableRequest->getRequest($_GET)));
        }

        if ($this->view->paid) {
            $locationsModel = new \Models\Cp\Locations($this->di);
            $this->view->startTime = $locationsModel->getLastPointTime($this->di['devId']);
        }

        $this->setView('cp/locations.htm');
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Locations');
    }

}
