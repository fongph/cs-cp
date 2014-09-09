<?php

namespace Controllers;

use \System\FlashMessages;
use \Models\Users;

class Index extends BaseController {

    public function contentAction() {
        if (isset($this->di['config']['contents'][$this->params['uri']])) {
            $contentModel = new \Models\Content($this->di);
            $path = $contentModel->getTemplatePath($this->params['uri']);
            $this->setView($path);
            $this->view->title = $this->di['t']->_($this->di['config']['contents'][$this->params['uri']]);
        } else {
            $this->error404();
        }
    }

    public function loginAction() {
        if ($this->di['auth']->hasIdentity()) {
            $this->redirect($this->di['router']->getRouteUrl('profile'));
        }

        if ($this->isPost() && isset($_POST['email'], $_POST['password']) && (strlen($_POST['email']) || strlen($_POST['password']))) {
            if (!strlen($_POST['email'])) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The username field is empty'));
            } else if (!strlen($_POST['password'])) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The password field is empty'));
            } else {
                $users = new Users($this->di);
                try {
                    if ($users->login($_POST['email'], $_POST['password'], isset($_POST['remember']))) {
                        $this->redirect($this->di['router']->getRouteUrl('profile'));
                    }
                } catch (\Models\UsersInvalidEmail $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Looks like that email address is not registered yet. Try to %1$sregister%2$s or retype again.', array(
                                '<a href="http://www.topspyapp.com/pricing-and-plans">',
                                '</a>'
                    )));
                } catch (\Models\UsersInvalidPassword $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The password you entered for the username %1$s is incorrect. %2$sLost your password%3$s?', array(
                                '<b>' . htmlspecialchars($_POST['email']) . '</b>',
                                '<a href="' . $this->di['router']->getRouteUrl('lostPassword') . '">',
                                '</a>'
                    )));
                } catch (\Models\UsersAccountLocked $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('This account has been locked'));
                }
            }
        }

        $this->setView('index/login.htm');
        $this->view->title = $this->di['t']->_('Login');
    }

    public function logoutAction() {
        $users = new Users($this->di);
        if ($this->di['auth']->hasIdentity()) {
            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('You have successfully logged out'));
            $users->logout();
        }
        $this->redirect($this->di['router']->getRouteUrl('main'));
    }

    public function supportAction() {
        $this->view->title = $this->di['t']->_('Support');

        $supportModel = new \Models\Support($this->di);

        if ($this->isPost() && isset($_POST['name'], $_POST['email'], $_POST['type'], $_POST['message'])) {
            try {
                $ticketId = $supportModel->submitTicket($_POST['name'], $_POST['email'], $_POST['type'], $_POST['message']);
                $this->view->success = true;

                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Your ticket #%1$s has been successfully sent!<br/> Our Support Team will contact you within 1 business day.', array('ticketId' => $ticketId)));
            } catch (\Models\SupportEmptyFieldException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Please, fill all the data carefully.'));
            } catch (\Models\SupportInvalidEmailException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Please, fill all the data carefully.'));
            } catch (\Models\SupportInvalidTypeException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Please, fill all the data carefully.'));
            } catch (\Models\MailSendException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Error during send email. Please try again later.'));
                logException($e, ROOT_PATH . 'mailExceptions.log');
            }
        }

        $this->view->types = $supportModel->getTypesList();
        $this->setView('index/support.htm');
    }
    
    public function localeAction() {
        if (isset($this->di['config']['locales'][$this->params['value']])) {
            $usersModel = new \Models\Users($this->di);
            $usersModel->setLocale($this->params['value']);
        }

        if (isset($_SERVER['HTTP_REFERER'])) {
            $this->redirect($_SERVER['HTTP_REFERER']);
        } else {
            $this->redirect($this->di['router']->getRouteUrl('main'));
        }
    }

    public function lostPasswordAction() {
        if ($this->di['auth']->hasIdentity()) {
            $this->redirect($this->di['router']->getRouteUrl('main'));
        }

        if (isset($_POST['email'])) {
            $usersModel = new \Models\Users($this->di);
            try {
                $usersModel->lostPasswordSend($_POST['email']);
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The confirmation link email has been sent to you. If it is not in your Inbox, check Spam, please!'));
                $this->redirect($this->di['router']->getRouteUrl('main'));
            } catch (\Models\UsersEmailNotFoundException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid email or there is no user registered with that email address'));
            } catch (\Models\MailSendException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Error during send email. Please try again later.'));
                logException($e, ROOT_PATH . 'mailExceptions.log');
            }
            $this->redirect($this->di['router']->getRouteUrl('lostPassword'));
        }

        $this->view->title = $this->di['t']->_('Lost Password');
        $this->setView('index/lostPassword.htm');
    }

    public function unlockAccountAction() {
        if ($this->di['auth']->hasIdentity()) {
            $this->redirect($this->di['router']->getRouteUrl('main'));
        }

        if (isset($_GET['email'], $_GET['key']) && strlen($_GET['key'])) {
            $usersModel = new \Models\Users($this->di);
            if ($usersModel->unlockAccount($_GET['email'], $_GET['key'])) {
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Your account has been unlocked. You may now log in.'));
            }
        }

        $this->redirect($this->di['router']->getRouteUrl('main'));
    }

    public function resetPasswordAction() {
        if ($this->di['auth']->hasIdentity()) {
            $this->redirect($this->di['router']->getRouteUrl('main'));
        }

        if (isset($_GET['email'], $_GET['key']) && strlen($_GET['key'])) {
            $usersModel = new \Models\Users($this->di);
            if ($usersModel->canRestorePassword($_GET['email'], $_GET['key'])) {
                if (isset($_POST['newPassword'], $_POST['newPassword2'])) {
                    try {
                        $usersModel->resetPassword($_GET['email'], $_POST['newPassword'], $_POST['newPassword2']);
                        $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Your password has been successfully changed!'));
                        $this->redirect($this->di['router']->getRouteUrl('main'));
                    } catch (\Models\UsersPasswordsNotEqualException $e) {
                        $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Your passwords do not match. Please try again!'));
                    } catch (\Models\UsersPasswordTooShortException $e) {
                        $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The password is too short. It must have at least 6 characters!'));
                    }
                }
            } else {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('You can\'t reset your password using this link!'));
                $this->redirect($this->di['router']->getRouteUrl('main'));
            }
        } else {
            $this->redirect($this->di['router']->getRouteUrl('main'));
        }

        $this->view->title = $this->di['t']->_('Reset Password');
        $this->setView('index/resetPassword.htm');
    }

}
