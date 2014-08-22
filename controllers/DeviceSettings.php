<?php

namespace Controllers;

use System\FlashMessages;

class DeviceSettings extends BaseController {

    protected $module = 'settings';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        $settingsModel = new \Models\Cp\Settings($this->di);

        if ($this->isPost()) {
            if (isset($_POST['phonesBlackList'], $_POST['phone'])) {
                try {
                    if ($settingsModel->addBlackListPhone($this->di['devId'], $_POST['phone'])) {
                        $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The phone number has been successfully added!'));
                    } else {
                        $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Error occurred during adding the phone number!'));
                    }
                } catch (\Models\Cp\SettingsInvalidPhoneNumberException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid phone number!'));
                } catch (\Models\Cp\SettingsPhoneNumberExistException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The phone number already exists on the list!'));
                }
            } else if (isset($_POST['deviceSettings'], $_POST['name'])) {
                $simNotifications = isset($_POST['simNotificactions']);

                if (isset($_POST['blackWords'])) {
                    $blackWords = $_POST['blackWords'];
                } else {
                    $blackWords = null;
                }

                try {
                    $result = $settingsModel->setDeviceSettings($this->di['devId'], $_POST['name'], $simNotifications, $blackWords);
                    if ($result === false) {
                        $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Error occurred during saving the changes!'));
                    } else if ($result) {
                        $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The settings have been successfully updated!'));
                    } else {
                        $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('No changes have been found!'));
                    }
                } catch (\Models\Cp\SettingsInvalidDeviceNameException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The device name must be between 1 and 32 characters long!'));
                }
            } else if (isset($_POST['lockDevice'], $_POST['password'])) {
                if ($settingsModel->lockDevice($this->di['devId'], $_POST['password']) === false) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Error occurred during locking the device!'));
                }

                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The password has been successfully added!'));
                $this->redirect($this->di['router']->getRouteUrl('settings'));
            }

            $this->redirect($this->di['router']->getRouteUrl('settings'));
        } else if (isset($_GET['removePhonesBlackList'])) {
            try {
                if ($settingsModel->removeBlackListPhone($this->di['devId'], $_GET['removePhonesBlackList']) === false) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Error occurred during deleting the phone number!'));
                }

                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The phone number has been successfully deleted!'));
            } catch (\Models\Cp\SettingsPhoneNumberNotFoundInListException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The phone is not on the list!'));
            }

            $this->redirect($this->di['router']->getRouteUrl('settings'));
        } else if (isset($_GET['rebootDevice'])) {
            $settingsModel->setRebootDevice($this->di['devId']);

            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Request to reboot device has been successfully sent!'));

            $this->redirect($this->di['router']->getRouteUrl('settings'));
        } else if (isset($_GET['rebootApp'])) {
            $settingsModel->setRebootApp($this->di['devId']);

            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Request to reboot application has been successfully sent to device!'));

            $this->redirect($this->di['router']->getRouteUrl('settings'));
        } else if (isset($_GET['delete'])) {
            $settingsModel->delete($this->di['devId']);

            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The device has been successfully removed from your account!'));

            $this->redirect($this->di['router']->getRouteUrl('profile'));
        }

        $this->view->data = $settingsModel->getSettings($this->di['devId']);

        $this->setView('cp/settings.htm');
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Phone Settings');
    }

}
