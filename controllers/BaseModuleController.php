<?php

namespace Controllers;

use CS\Devices\Manager as DevicesManager,
    System\FlashMessages,
    Models\Modules;

abstract class BaseModuleController extends BaseController
{

    protected $module = '';
    protected $paid = false;

    protected function initCP()
    {
        $devicesManager = new DevicesManager($this->di['db']);
        $devicesModel = new \Models\Devices($this->di);

        $devices = $devicesManager->getUserActiveDevices($this->auth['id']);
        $this->di->set('devicesList', $devices);

        $devId = $devicesModel->getCurrentDevId();
        $this->di->set('devId', $devId);
        $this->di->set('currentDevice', $devices[$devId]);

        if ($devId === null) {
            $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('No devices have been added to your Control Panel!'));
            $this->redirect($this->di['router']->getRouteUrl('profile'));
        } else if (isset($this->di['config']['modules'][$this->module])) {
            $this->moduleCheck();
        }
    }

    protected function moduleCheck()
    {
        $modulesModel = new Modules($this->di);
        
        if ($modulesModel->isModuleActive($this->module) === false) {
            $this->redirect($this->di['router']->getRouteUrl(Modules::CALLS));
        }

        $this->view->paid = $this->isModulePaid();
        if (!$this->view->paid) {
            if ($this->di->getRouter()->getRouteName() != $this->module) {
                $this->redirect($this->di->getRouter()->getRouteUrl($this->module));
            }

            $this->view->packageLink = '';
        }
    }

    protected abstract function isModulePaid();
    
    protected function buildCpMenu()
    {
        $modulesModel = new Modules($this->di);
        $this->view->cpMenu = array();

        foreach ($this->di['config']['modules'] as $routeName => $name) {
            if ($modulesModel->isModuleActive($routeName) !== false) {
                $this->view->cpMenu[$this->di['router']->getRouteUrl($routeName)] = array(
                    'name' => $this->di['t']->_($name),
                    'class' => $routeName,
                    'active' => $routeName == $this->module
                );
            }
        }
    }

    protected function checkDisplayLength($value = 10)
    {
        if ($value !== $this->auth['records_per_page']) {
            $usersModel = new \Models\Users($this->di);
            $usersModel->setRecordsPerPage($value);
            $usersModel->reLogin();
        }
    }

}
