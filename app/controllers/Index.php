<?php

namespace Controllers;

use Models\Users,
    Models\Billing,
    Models\Devices,
    CS\Users\UsersNotes,
    System\FlashMessages,
    CS\Users\UsersManager,
    Components\WizardRouter,
    CS\Devices\Manager as DeviceManager;

class Index extends BaseController
{

    public function contentAction()
    {
        if (isset($this->di['config']['contents'][$this->params['uri']])) {
            $contentModel = new \Models\Content($this->di);
            $path = $contentModel->getTemplatePath($this->params['uri']);
            $this->setView($path);
            $this->view->title = $this->di['t']->_($this->di['config']['contents'][$this->params['uri']]);
            $this->view->norobots = (isset($this->di['config']['norobots'][$this->params['uri']])
                    and $this->di['config']['norobots'][$this->params['uri']]) ? true : false;
        } else {
            $this->error404();
        }
    }

    public function loginAction()
    {
        if ($this->di->getAuth()->hasIdentity()) {
            $this->loginRedirect();
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
                        $users->setAuthCookie();
                        $this->loginRedirect();
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
        $this->view->title = $this->di['t']->_('LoginTitle');
    }

    public function loginRedirect()
    {
        if ($this->di->get('isWizardEnabled')) {

            if ($this->getRequest()->get('redirect'))
                $this->redirect($this->getRequest()->get('redirect'));

            $devicesManager = new DeviceManager($this->di->get('db'));
            $devices = $devicesManager->getUserActiveDevices($this->di->getAuth()->getIdentity()['id']);

            foreach ($devices as $device)
                if ($device['active'])
                    $this->redirect($this->di->getRouter()->getRouteUrl('cp'));

            $billing = new Billing($this->di);
            $packages = $billing->getAvailablePackages($this->di->getAuth()->getIdentity()['id']);

            if (count($packages) == 1) {
                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PLATFORM, array('licenseId' => $packages[0]['license_id'])));
            } elseif (count($packages)) {
                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PACKAGE));
            } else {
                $this->redirect($this->di->getRouter()->getRouteUrl('profile'));
            }
        } else
            $this->redirect($this->di->getRouter()->getRouteUrl('profile'));
    }

    public function logoutAction()
    {
        $this->checkDemo(\CS\Settings\GlobalSettings::getMainURL($this->di['config']['site']), false);

        $users = new Users($this->di);
        if ($this->di['auth']->hasIdentity()) {
            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('You have successfully logged out'));
            $users->logout();
        }
        $this->redirect($this->di['router']->getRouteUrl('main'));
    }

    public function supportAction()
    {
        $this->checkDemo($this->di['router']->getRouteUrl('cp'));
        $this->checkSupportMode();

        $this->view->title = $this->di['t']->_('Support');

        $supportModel = new \Models\Support($this->di);

        $_user = $supportModel -> getDI() -> getAuth()->getIdentity();
        if(!isset($_user['id']) 
                || 
          (!isset($_user['login']) and empty($_user['login']))) 
                $this -> loginAction();
        
        $um = new UsersManager( $this -> di -> get('db') );
        $info_user = $um -> getUser( (int)$_user['id'] );
        
        $userName = (strlen($info_user -> getName())) ? $info_user -> getName() : ' '; 
        
        if ($this->getRequest()->hasPost('type', 'message')) { // 'email', 'name',
            try {
                $ticketId = $supportModel->submitTicket(
                        $userName,
                        $_user['login'],
                        $this->getRequest()->post('type'), 
                        htmlspecialchars(strip_tags(trim( $this->getRequest()->post('message') )))
                ); // $this->getRequest()->post('email'),  $this->getRequest()->post('name'), 

                $this->di->get('usersNotesProcessor')->supportTicketSent($ticketId);

                $this->view->success = true;

                // Your ticket #%1$s has been successfully sent!<br/> Our Support Team will contact you within 1 business day.
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Ticket #%1$s has been successfully sent.<br/> Our support representative will contact you as soon as possible.', array('ticketId' => $ticketId)));
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
            $usersModel->setLocale($this->params['value'], !$this->demo);
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
            $usersManager = $this->di['usersManager'];
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
        $this->checkDemo($this->di['router']->getRouteUrl('main'), false);
        
        $usersModel = new Users($this->di);
        if ($this->getRequest()->hasGet('id', 'h', 'admin_id') &&
                $usersModel->directLogin($this->getRequest()->get('id'), $this->getRequest()->get('admin_id'), $this->getRequest()->get('h'), $this->getRequest()->hasGet('support_mode'))) {

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

    /**
     * Instructions
     */
    public function rootingAndroidAction() {
        $this->setView('instructions/root-android-instructions.html');
        $this->view->title = $this->di['t']->_('Rooting Android');
        $this->view->previos = $this -> pagePrev();
    }
    
    public function superuserAction() {
        $this->setView('instructions/superuser.html');
        $this->view->title = $this->di['t']->_('Granting Superuser Rights');
        $this->view->previos = $this -> pagePrev();
    }
    
    public function pagePrev() {
        $previous = $this->di['config']['domain'];// "javascript:history.go(-1)";
        if(isset($_SERVER['HTTP_REFERER'])) {
            $previous = $_SERVER['HTTP_REFERER'];
        }
        return $previous;
    }
    
    public function installingAndroidAction() {
        $this->setView('instructions/installingAndroid.html');
        $this->view->title = $this->di['t']->_('Android Installation Guide');
}
    
    public function installingIosAction() {
       $this->setView('instructions/installingIos.html');
       $this->view->title = $this->di['t']->_('iOS Installation Guide');
    }
    
    public function wizardAndroidAction() {
        $this->setView('instructions/wizardAndroid.html');
        $this->view->title = $this->di['t']->_('Android Installation Guide');
        $this->view->title_page = $this->di['t']->_('Android Installation Guide for Support');
        $this->view->code = 4544;
    }
    public function wizardIosAction() {
        $this->setView('instructions/wizardIos.html');
        $this->view->title = $this->di['t']->_('iOS Installation Guide');
        $this->view->title_page = $this->di['t']->_('iOS Installation Guide for Support');
        $this->view->code = 2629;
    }
    public function wizardIcloudAction() {
        $this->setView('instructions/wizardIcloud.html');
        $this->view->title = $this->di['t']->_('iOS iCloud Installation Guide');
        $this->view->title_page = $this->di['t']->_('iOS iCloud Installation Guide for Support');
    }
    
    public function activateLocationIosAction() {
        $this->setView('instructions/activateLocationIos.html');
        $this->view->title = $this->di['t']->_('How to Activate Location');
        $this->view->title_page = $this->di['t']->_('How to Activate Location');
        $this->view->previos = $this -> pagePrev();
    }
}
