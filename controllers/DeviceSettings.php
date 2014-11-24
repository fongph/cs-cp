<?php

namespace Controllers;

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
                try {
                    $settingsModel->lockDevice($this->di['devId'], $this->getRequest()->post('password'));

                    $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The password has been successfully added!'));
                } catch (\Models\Cp\Settings\InvalidPasswordException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid device password!'));
                }
            }

            $this->redirect($this->di['router']->getRouteUrl('settings'));
        } else if ($this->getRequest()->hasGet('removePhonesBlackList')) {
            try {
                $settingsModel->removeBlackListPhone($this->di['devId'], $this->getRequest()->get('removePhonesBlackList'));

                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The phone number has been successfully deleted!'));
            } catch (\Models\Cp\Settings\PhoneNumberNotFoundInListException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The phone is not on the list!'));
            }

            $this->redirect($this->di['router']->getRouteUrl('settings'));
        } else if ($this->getRequest()->hasGet('rebootDevice')) {
            $settingsModel->setRebootDevice($this->di['devId']);

            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Request to reboot device has been successfully sent!'));

            $this->redirect($this->di['router']->getRouteUrl('settings'));
        } else if ($this->getRequest()->hasGet('rebootApp')) {
            $settingsModel->setRebootApp($this->di['devId']);

            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Request to reboot application has been successfully sent to device!'));

            $this->redirect($this->di['router']->getRouteUrl('settings'));
        } else if ($this->getRequest()->hasGet('delete')) {
            $devicesModel = new \Models\Devices($this->di);
            $devicesModel->delete($this->di['devId']);

            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The device has been successfully removed from your account!'));
            $this->redirect($this->di['router']->getRouteUrl('profile'));
        }

        $this->view->data = $settingsModel->getSettings($this->di['devId']);

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
