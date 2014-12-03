<?php

namespace Controllers;

use Models\Users,
    System\FlashMessages,
    CS\Users\UsersManager;

class Index extends BaseController
{

    public function contentAction()
    {
        if (isset($this->di['config']['contents'][$this->params['uri']])) {
            $contentModel = new \Models\Content($this->di);
            $path = $contentModel->getTemplatePath($this->params['uri']);
            $this->setView($path);
            $this->view->title = $this->di['t']->_($this->di['config']['contents'][$this->params['uri']]);
        } else {
            $this->error404();
        }
    }

    public function loginAction()
    {
        if ($this->di['auth']->hasIdentity()) {
            $this->redirect($this->di['router']->getRouteUrl('profile'));
        }

        if ($this->getRequest()->isPost()) {
            $email = $this->getRequest()->post('email', '');
            $password = $this->getRequest()->post('password', '');
            $remember = $this->getRequest()->post('remember', 0);

            if (!strlen($email)) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The username field is empty'));
            } else if (!strlen($password)) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The password field is empty'));
            } else {
                $users = new Users($this->di);
                try {
                    if ($users->login($email, $password, $remember)) {
                        $this->redirect($this->di['router']->getRouteUrl('profile'));
                    }
                } catch (\CS\Users\UserNotFoundException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Looks like that email address is not registered yet. Try to %1$sregister%2$s or retype again.', array(
                                '<a href="' . $this->di['config']['registration'] . '">',
                                '</a>'
                    )));
                } catch (\CS\Users\InvalidPasswordException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The password you entered for the username %1$s is incorrect. %2$sLost your password%3$s?', array(
                                '<b>' . htmlspecialchars($this->getRequest()->post('email')) . '</b>',
                                '<a href="' . $this->di['router']->getRouteUrl('lostPassword') . '">',
                                '</a>'
                    )));
                } catch (\CS\Users\UserLockedException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('This account has been locked'));
                }
            }
        }

        $this->setView('index/login.htm');
        $this->view->title = $this->di['t']->_('Login');
    }

    public function logoutAction()
    {
        $users = new Users($this->di);
        if ($this->di['auth']->hasIdentity()) {
            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('You have successfully logged out'));
            $users->logout();
        }
        $this->redirect($this->di['router']->getRouteUrl('main'));
    }

    public function supportAction()
    {
        $this->view->title = $this->di['t']->_('Support');

        $supportModel = new \Models\Support($this->di);

        if ($this->getRequest()->hasPost('name', 'email', 'type', 'message')) {
            try {

                $ticketId = $supportModel->submitTicket(
                        $this->getRequest()->post('name'), $this->getRequest()->post('email'), $this->getRequest()->post('type'), $this->getRequest()->post('message')
                );

                $this->view->success = true;

                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Your ticket #%1$s has been successfully sent!<br/> Our Support Team will contact you within 1 business day.', array('ticketId' => $ticketId)));
            } catch (\Models\Support\SupportEmptyFieldException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Please, fill all the data carefully.'));
            } catch (\Models\Support\SupportInvalidEmailException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Please, fill all the data carefully.'));
            } catch (\Models\Support\SupportInvalidTypeException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Please, fill all the data carefully.'));
            } catch (\CS\Mail\MailSendException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Error during send email. Please try again later.'));
            }
        }

        $this->view->types = $supportModel->getTypesList();
        $this->view->refundPolicyUrl = \CS\Settings\GlobalSettings::getRefundPolicyPageURL($this->di['config']['site']);
        $this->setView('index/support.htm');
    }

    public function localeAction()
    {
        if (isset($this->di['config']['locales'][$this->params['value']])) {
            $usersModel = new \Models\Users($this->di);
            $usersModel->setLocale($this->params['value']);
        }

        $referer = $this->getRequest()->server('HTTP_REFERER');

        if ($referer !== null) {
            $this->redirect($referer);
        } else {
            $this->redirect($this->di['router']->getRouteUrl('main'));
        }
    }

    public function lostPasswordAction()
    {
        if ($this->di['auth']->hasIdentity()) {
            $this->redirect($this->di['router']->getRouteUrl('main'));
        }

        $email = $this->getRequest()->post('email');

        if ($email !== null) {
            $usersManager = new UsersManager($this->di->get('db'));
            $usersManager->setSender($this->di['mailSender']);

            try {
                $usersManager->lostPassword($this->di['config']['site'], $email);
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The confirmation link email has been sent to you. If it is not in your Inbox, check Spam, please!'));
                $this->redirect($this->di['router']->getRouteUrl('main'));
            } catch (\CS\Users\UsersEmailNotFoundException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid email or there is no user registered with that email address'));
            } catch (MailSendException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Error during send email. Please try again later.'));
            }
        }

        $this->view->title = $this->di['t']->_('Lost Password');
        $this->setView('index/lostPassword.htm');
    }

    public function unlockAccountAction()
    {
        if ($this->di['auth']->hasIdentity()) {
            $this->redirect($this->di['router']->getRouteUrl('main'));
        }

        if ($this->getRequest()->hasGet('email', 'key')) {
            $usersManager = new UsersManager($this->di->get('db'));

            $email = $this->getRequest()->get('email');
            $key = $this->getRequest()->get('key');

            if ($usersManager->unlockAccount($this->di['config']['site'], $email, $key)) {
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Your account has been unlocked. You may now log in.'));
            }
        }

        $this->redirect($this->di['router']->getRouteUrl('main'));
    }

    public function resetPasswordAction()
    {
        if ($this->di['auth']->hasIdentity()) {
            $this->redirect($this->di['router']->getRouteUrl('main'));
        }

        if (!$this->getRequest()->hasGet('email', 'key')) {
            $this->redirect($this->di['router']->getRouteUrl('main'));
        }

        $usersManager = new UsersManager($this->di->get('db'));

        $email = $this->getRequest()->get('email');
        $key = $this->getRequest()->get('key');

        if (!$usersManager->canResetPassword($this->di['config']['site'], $email, $key)) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('You can\'t reset your password using this link!'));
            $this->redirect($this->di['router']->getRouteUrl('main'));
        }

        if ($this->getRequest()->hasPost('newPassword', 'newPassword2')) {
            $password = $this->getRequest()->post('newPassword');
            $passwordConfirm = $this->getRequest()->post('newPassword2');

            try {
                if ($usersManager->resetPassword($this->di['config']['site'], $email, $key, $password, $passwordConfirm)) {
                    $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Your password has been successfully changed!'));
                } else {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('You can\'t reset your password using this link!'));
                }
                $this->redirect($this->di['router']->getRouteUrl('main'));
            } catch (PasswordsNotEqualException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Your passwords do not match. Please try again!'));
            } catch (PasswordTooShortException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The password is too short. It must have at least 6 characters!'));
            }
        }

        $this->view->title = $this->di['t']->_('Reset Password');
        $this->setView('index/resetPassword.htm');
    }

    public function directLoginAction()
    {
        $usersModel = new Users($this->di);
        if ($this->getRequest()->hasGet('id', 'h') &&
                $usersModel->directLogin($this->getRequest()->get('id'), $this->getRequest()->get('h'))) {

            $deviceId = $this->getRequest()->get('device');

            if ($deviceId !== null) {
                $devicesModel = new \Models\Devices($this->di);
                $devicesModel->setCurrentDevId($deviceId);
                $this->redirect($this->di['router']->getRouteUrl('cp'));
            } else {
                $this->redirect($this->di['router']->getRouteUrl('main'));
            }
        }

        $this->error404();
    }

}
