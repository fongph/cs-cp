<?php

namespace Controllers;

use System\FlashMessages,
    CS\Users\UsersManager,
    CS\Users\PasswordsNotEqualException,
    CS\Users\PasswordTooShortException,
    CS\Users\InvalidPasswordException,
    CS\Devices\Manager as DeviceManager;

class Profile extends BaseController
{

    public function indexAction()
    {
        $this->view->title = $this->di->getTranslator()->_('Your Profile');

        if ($this->getRequest()->isPost()) {
            if ($this->getRequest()->post('settings') !== null) {
                $this->processSettings();
            } else if ($this->getRequest()->post('changePassword') !== null) {
                $this->processChangePassword();
            }
        }

        $usersModel = new \Models\Users($this->di);
        if($this->di->get('isWizardEnabled')){
            $deviceManager = new DeviceManager($this->di->get('db'));
            $this->view->availabledevices = $deviceManager->getUserActiveDevices($this->auth['id']);
            
        } else $this->view->availabledevices = false;

        $this->view->recordsPerPage = $this->auth['records_per_page'];
        $this->view->recordsPerPageList = $usersModel->getRecordsPerPageList();
        $this->setView('profile/index.htm');
    }

    private function processSettings()
    {
        $usersModel = new \Models\Users($this->di);

        $settings = array();

        if ($this->getRequest()->post('locale') !== null) {
            $settings['locale'] = $this->getRequest()->post('locale');
        }

        if ($this->getRequest()->post('recordsPerPage') !== null) {
            $settings['recordsPerPage'] = $this->getRequest()->post('recordsPerPage');
        }

        if (count($settings) && $usersModel->setSettings($settings)) {
            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Your settings have been successfully updated!'));
        }

        $this->redirect($this->di['router']->getRouteUrl('profile'));
    }

    private function processChangePassword()
    {
        if ($this->getRequest()->hasPost('oldPassword', 'newPassword', 'newPassword2')) {
            $usersManager = new UsersManager($this->di->get('db'));

            try {
                $usersManager->updatePassword(
                        $this->auth['id'],
                        $this->getRequest()->post('oldPassword'),
                        $this->getRequest()->post('newPassword'),
                        $this->getRequest()->post('newPassword2')
                );

                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Your password has been successfully changed!'));
            } catch (PasswordsNotEqualException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Please enter the same password in the two password fields!'));
            } catch (PasswordTooShortException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Password is too short, must be 6 characters or more!'));
            } catch (InvalidPasswordException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid old password!'));
            }
        }
    }

}
