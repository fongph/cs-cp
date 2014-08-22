<?php

namespace Controllers;

use System\FlashMessages;

class Profile extends BaseController {

    public function indexAction() {
        $this->view->title = $this->di['t']->_('Your Profile');

        if ($this->isPost()) {
            if (isset($_POST['settings'])) {
                $this->_processSettings();
            } else if (isset($_POST['changePassword'])) {
                $this->_processChangePassword();
            }
        }

        $usersModel = new \Models\Users($this->di);
        
        $this->view->recordsPerPage = $this->auth['records_per_page'];
        $this->view->recordsPerPageList = $usersModel->getRecordsPerPageList();
        $this->setView('profile/index.htm');
    }

    private function _processSettings() {
        if (isset($_POST['locale'])) {
            if (array_key_exists($_POST['locale'], $this->di['config']['locales'])) {
                $settings = array(
                    'locale' => $_POST['locale'],
                    'recordsPerPage' => $_POST['recordsPerPage']
                );

                $usersModel = new \Models\Users($this->di);
                if ($usersModel->updateSettings($settings) !== false) {
                    $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Your settings have been successfully updated!'));
                }
                $this->redirect($this->di['router']->getRouteUrl('profile'));
            }
        }
    }
    
    private function _processChangePassword(){
        if (isset($_POST['oldPassword'], $_POST['newPassword'], $_POST['newPassword2'])) {
            if ($_POST['newPassword'] !== $_POST['newPassword2']) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Please enter the same password in the two password fields!'));
            } elseif (strlen($_POST['newPassword']) < 6) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Password is too short, must be 6 characters or more!'));
            } else {
                $usersModel = new \Models\Users($this->di);
                
                if (!$usersModel->isPassword($_POST['oldPassword'])) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid old password!'));
                } else {
                    $usersModel->changePassword($_POST['newPassword']);
                    $usersModel->reLogin();
                    $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Your password has been successfully changed!'));
                }
            }
        }
    }

}
