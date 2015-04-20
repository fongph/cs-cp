<?php

namespace Controllers;

use CS\Devices\Manager as DevicesManager,
    System\FlashMessages,
    Models\Modules;

abstract class BaseModuleController extends BaseController
{

    protected $module = '';
    protected $paid = false;

    protected $plans = [
        'basic' => [
            'calls', 'sms', 'locations', 'browserBookmarks',
            'browserHistory', 'applications', 'emails',
            'calendar', 'contacts', 'photos',
        ],
        'premium' => [
            'facebook', 'keylogger', 'videos', 
            'viber', 'skype', 'whatsapp',
            'instagram', 'smsCommands', 'kik',
        ] // 'settings',
    ];
    
    protected function initCP()
    {
        $devicesManager = new DevicesManager($this->di['db']);
        $devicesModel = new \Models\Devices($this->di);
        
        $showDeletedDevices = $this->supportMode;
        $devices = $devicesManager->getUserActiveDevices($this->auth['id'], $showDeletedDevices);
        $this->di->set('devicesList', $devices);

        if (($devId = $devicesModel->getCurrentDevId()) === null) {
            $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('No devices have been added to your Control Panel!'));
            $this->redirect($this->di['router']->getRouteUrl('profile'));
        }
        
        if (!isset($this->di['config']['modules'][$this->module])) {
            throw new \Exception("Module not found!");
        }

        $this->di->set('devId', $devId);
        $this->di->set('currentDevice', $devices[$devId]);
        
        $this->moduleCheck();
    }

    protected function moduleCheck()
    {
        if (!$this->supportMode && $this->di['currentDevice']['package_name'] == null) {
            $this->postAction();
            $this->setView('cp/noPackage.htm');
            $this->view->title = $this->di['t']->_('No Plan');
            $this->response();
            die;
        }
        
        $modulesModel = new Modules($this->di);

        if ($modulesModel->isModuleActive($this->module) === false) {
            $this->redirect($this->di['router']->getRouteUrl(Modules::CALLS));
        }
        
        $this->view->paid = $this->supportMode || $this->isModulePaid();
    }

    protected abstract function isModulePaid();

    protected function isDetectedPlan( $_route ) {
        $_point = false;
        if(isset($this -> di['config']['demo']) 
                and $this -> di['config']['demo']) {
            if (!empty($this -> di['devicesList'])) :
                if($this -> di['devicesList'][ $this -> di['devId'] ]['os'] == 'android' and !empty($_route)) {
                    
                    if(in_array($_route, $this -> plans['basic'])) {
                        $_point = 'color-green';
                    }
                    else if(in_array($_route, $this -> plans['premium'])) {
                        $_point = 'color-black';
                    }   
                    
                }
            endif;
        }
        
        return $_point;
    }
    
    protected function buildCpMenu()
    {
        $modulesModel = new Modules($this->di);
        $this->view->cpMenu = array();
        
        foreach ($this->di['config']['modules'] as $routeName => $name) {
            if ($modulesModel->isModuleActive($routeName) !== false) {
                $this->view->cpMenu[$this->di['router']->getRouteUrl($routeName)] = array(
                    'name' => $this->di['t']->_($name),
                    'class' => $routeName,
                    'active' => $routeName == $this->module,
                    'point' => $this ->isDetectedPlan($routeName),
                );
            }
        }
    }

}
