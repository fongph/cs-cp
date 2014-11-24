<?php

namespace Controllers;

use Models\Modules,
    CS\Devices\Limitations;

class Locations extends BaseModuleController
{

    protected $module = Modules::LOCATIONS;

    protected function init()
    {
        parent::init();
        $this->initCP();
    }

    public function indexAction()
    {
        if ($this->getRequest()->isAjax()) {
            if (!$this->getRequest()->hasPost('date')) {
                return $this->makeJSONResponse(array('success' => 0));
            }

            $locationsModel = new \Models\Cp\Locations($this->di);
            if (($data = $locationsModel->getPoints($this->di['devId'], $this->getRequest()->post('date'))) !== false) {
                return $this->makeJSONResponse(array(
                            'success' => 1,
                            'result' => $data
                ));
            } else {
                return $this->makeJSONResponse(array('success' => 0));
            }

            $dataTableRequest = new \System\DataTableRequest();
            $this->makeJSONResponse($locationsModel->getDataTableData($this->di['devId'], $dataTableRequest->getRequest($this->getRequest()->get())));
        }

        $locationsModel = new \Models\Cp\Locations($this->di);
        $this->view->startTime = $locationsModel->getLastPointTime($this->di['devId']);

        $this->setView('cp/locations.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Locations');
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::GPS);
    }
    
}
