<?php

namespace Controllers;

use CS\Models\Device\DeviceICloudRecord;
use CS\Models\Device\DeviceRecord;
use System\FlashMessages,
    Models\Modules;

class DeviceSettings extends BaseModuleController
{

    protected $module = Modules::SETTINGS;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $settingsModel = new \Models\Cp\Settings($this->di);

        if ($this->getRequest()->isPost()) {
            $this->checkDemo($this->di['router']->getRouteUrl('settings'));
            
            if ($this->getRequest()->hasPost('phonesBlackList', 'phone')) {
                try {
                    if ($settingsModel->addBlackListPhone($this->di['devId'], $this->getRequest()->post('phone'))) {
                        $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The phone number has been successfully added!'));
                    } else {
                        $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Error occurred during adding the phone number!'));
                    }
                } catch (\Models\Cp\Settings\InvalidPhoneNumberException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid phone number!'));
                } catch (\Models\Cp\Settings\PhoneNumberExistException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The phone number already exists on the list!'));
                }
            } else if ($this->getRequest()->hasPost('deviceSettings', 'name')) {
                $simNotifications = $this->getRequest()->hasPost('simNotificactions');
                $blackWords = $this->getRequest()->post('blackWords', '');

                $devicesModel = new \Models\Devices($this->di);
                try {
                    $devicesModel->setDeviceName($this->di['devId'], $this->getRequest()->post('name'));
                    $settingsModel->setDeviceSettings($this->di['devId'], $simNotifications, $blackWords);

                    $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The settings have been successfully updated!'));
                } catch (\Models\Devices\InvalidDeviceNameException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The device name must be between 1 and 32 characters long!'));
                }
            } else if ($this->getRequest()->hasPost('lockDevice', 'password')) {
                $this->checkDemo($this->di['router']->getRouteUrl('settings'));
                
                try {
                    $settingsModel->lockDevice($this->di['devId'], $this->getRequest()->post('password'));

                    $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The password has been successfully added!'));
                } catch (\Models\Cp\Settings\InvalidPasswordException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid device password!'));
                }
            }

            $this->redirect($this->di['router']->getRouteUrl('settings'));
        } else if ($this->getRequest()->hasGet('removePhonesBlackList')) {
            $this->checkDemo($this->di['router']->getRouteUrl('settings'));
            
            try {
                $settingsModel->removeBlackListPhone($this->di['devId'], $this->getRequest()->get('removePhonesBlackList'));

                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The phone number has been successfully deleted!'));
            } catch (\Models\Cp\Settings\PhoneNumberNotFoundInListException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The phone is not on the list!'));
            }

            $this->redirect($this->di['router']->getRouteUrl('settings'));
        } else if ($this->getRequest()->hasGet('rebootDevice')) {
            $this->checkDemo($this->di['router']->getRouteUrl('settings'));
            
            $settingsModel->setRebootDevice($this->di['devId']);

            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Request to reboot device has been successfully sent!'));

            $this->redirect($this->di['router']->getRouteUrl('settings'));
        } else if ($this->getRequest()->hasGet('rebootApp')) {
            $this->checkDemo($this->di['router']->getRouteUrl('settings'));
            
            $settingsModel->setRebootApp($this->di['devId']);

            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Request to reboot application has been successfully sent to device!'));

            $this->redirect($this->di['router']->getRouteUrl('settings'));
        } else if ($this->getRequest()->hasGet('delete')) {
            $this->checkDemo($this->di['router']->getRouteUrl('settings'));
            
            $devicesModel = new \Models\Devices($this->di);
            $devicesModel->delete($this->di['devId']);

            try {
                $deviceRecord = new DeviceRecord($this->di['db']);
                $deviceRecord->load($this->di['devId']);

                (new \Models\Users($this->di))
                    ->addSystemNote($this->auth['id'],
                        "Delete device from CP {$deviceRecord->getName()} " . json_encode(array(
                            'device_id' => $this->di['devId'],
                        ))
                    );
            } catch(\Exception $e){}
            
            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The device has been successfully removed from your account!'));
            $this->redirect($this->di['router']->getRouteUrl('profile'));
        }

        $this->view->currentDevice = $this->di->get('currentDevice');
        $this->view->data = $settingsModel->getSettings($this->di['devId']);
        
        try{$this->view->iCloudRecord = new DeviceICloudRecord($this->di->get('db'));
            if($this->view->currentDevice['os'] == 'icloud')
                $this->view->iCloudRecord->loadByDevId($this->di->get('devId')); 
        }catch(\Exception $e){};

        $this->setView('cp/settings.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Device Settings');
    }
    
    protected function isModulePaid()
    {
        return true;
    }

}