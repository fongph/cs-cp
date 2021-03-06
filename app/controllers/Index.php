<?php

namespace Controllers;

use Models\Content;
use Models\Users,
    Models\Billing,
    Models\Devices,
    Models\Modules,
    CS\Users\FreeTrialLinks,
    CS\Users\UsersNotes,
    System\FlashMessages,
    CS\Users\UsersManager,
    Components\WizardRouter,
    CS\Devices\Manager as DeviceManager;


class Index extends BaseController
{

    public function contentAction()
    {
        if (isset($this->di['config']['contents']['names'][$this->params['uri']]) && !$this->demo) {
            
            if ($this->auth === null && in_array($this->params['uri'], $this->di['config']['contents']['auth'])) {
                
                $this->di->getFlashMessages()->add(\System\FlashMessages::ERROR, "Access denied! Please, log in to your Control Panel account to view the page.");

                $this->redirect($this->di->getRouter()->getRouteUrl('main').'?redirect='.$this->params['uri']);

            } else if (in_array($this->params['uri'], ['instructions/uninstall-pumpic-ios.html', 
                                                'instructions/uninstall-pumpic-android.html'])) {
                $this->instructionUninstall();
            }

            if(in_array($this->params['uri'], ['instructions/activate-location-android.html',
                                                'instructions/activate-location-ios.html'])) {
                $this->view->link = $this->pagePrev();
            } else if(in_array($this->params['uri'], ['instructions/keylogger-activation.html'])
                    and $this->getRequest()->hasGet('activate')) {
                $this->view->link = $this->di['router']->getRouteUrl('activate-keylogger').'?activate=1';
            }
            
            $contentModel = new \Models\Content($this->di);
            $path = $contentModel->getTemplatePath($this->params['uri']);
            $this->setView($path);
            $this->view->title = $this->di['t']->_($this->di['config']['contents']['names'][$this->params['uri']]);
            
            if(in_array($this->params['uri'], ['instructions/prepare-ios-device-without-jailbreak.html'])){
                $this->view->link = $this->di->getRouter()->getRouteUrl(Modules::CALLS);
                $this->view->startMonitoring = true;
            }
            if(in_array($this->params['uri'], ['instructions/installing-android.html',
                                                'instructions/installing-ios.html'])) {
                $this->setLayout('content/bordered-content.html');
                $this->view->startMonitoring = true;
                $this->view->link = $this->di->getRouter()->getRouteUrl('calls');
            }
            if(in_array($this->params['uri'], ['instructions/rooting-android.html',
                                                    'instructions/granting-superuser-rights.html'])) {
                $this->setLayout('content/bordered-content.html');
                $this->view->previos = $this->view->link = $this->pagePrev();

            }
            if(in_array($this->params['uri'], ['instructions/wizard-android.html']) ) {
                $this->view->code = 4544;
            }  
            if(in_array($this->params['uri'], ['instructions/wizard-ios.html']) ) {
                $this->view->code = 2629;
            }
            if(in_array($this->params['uri'], ['instructions/keylogger-activation.html'])) {
                $this->setLayout('content/instructions-keylogger-content.html');
            } else
                $this->setLayout('content/bordered-content.html');
        } else {
            $this->error404();
        }
    }

    public function instructionUninstall() 
    {
        $contentModel = new \Models\Content($this->di);
        $path = $contentModel->getTemplatePath($this->params['uri']);
        $this->setView($path);
        $this->view->title = $this->di['t']->_($this->di['config']['contents']['names'][$this->params['uri']]);
        $this->view->button = false;
    }
    
    public function loginAction()
    {
        $this->view->norobots = false;
        
        if ($this->di->getAuth()->hasIdentity()) {
            $this->loginRedirect();
        }
        //if user received email "unfinishedPurchaseNotification", that his buying not completed, but it not true
        if ($this->getRequest()->get('alreadycompletedpurchase')){
            $completedPurchaseUserEmail = $this->getRequest()->get('alreadycompletedpurchase');
            if (filter_var($completedPurchaseUserEmail, FILTER_VALIDATE_EMAIL)) {
                $users = new Users($this->di);
                if ($users->alreadyCompletedPurchaseUserExist($completedPurchaseUserEmail)){
                    $users->updateAlreadyCompletedPurchase($completedPurchaseUserEmail);
                } else {
                    $users->addAlreadyCompletedPurchase($completedPurchaseUserEmail);
                }
           
            }
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

                        $this->di['freeTrialLinks']->setAccessCookie(FreeTrialLinks::HIDDEN);

                        $users->setAuthCookie();
                        $this->loginRedirect();
                    }
                } catch (\CS\Users\UserNotFoundException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Looks like that email address is not registered yet. Try to %1$sregister%2$s or retype again.', array(
                                '<a href="' . $this->di['config']['url']['registration'] . '">',
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
        $this->view->title = $this->di['t']->_('Pumpic Control Panel: Log in');
        $this->view->descriptionMeta = 'Log in to Control Panel and access all Pumpic monitoring features. Protect your child online with Pumpic.com';
    }

    public function loginRedirect()
    {
        if ($this->di->get('isWizardEnabled')) {

            if ($this->getRequest()->get('redirect'))
                $this->redirect($this->getRequest()->get('redirect'));

            $devicesManager = new DeviceManager($this->di->get('db'));
            $devices = $devicesManager->getUserActiveDevices($this->di->getAuth()->getIdentity()['id']);

            foreach ($devices as $device)
                if ($device['active']){
//                    instead redirect to cp/calls
                        $cp = new CP($this->di);
                        $cp->mainAction();
                }

            $billing = new Billing($this->di);
            $packages = $billing->getAvailablePackages($this->di->getAuth()->getIdentity()['id']);

            if (count($packages) == 1) {
                if ($packages[0]['platform'] != 'no') {
                    $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_SETUP, array('licenseId' => $packages[0]['license_id'], 'platform' => $packages[0]['platform'])));
                } else {
                    $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PLATFORM, array('licenseId' => $packages[0]['license_id'])));
                }
            }elseif (count($packages)) {
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
        $this->checkDemo($this->di['router']->getRouteUrl('calls'));
        $this->checkSupportMode();

        $this->view->title = $this->di['t']->_('Support');

        $supportModel = new \Models\Support($this->di);

        $_user = $supportModel->getDI()->getAuth()->getIdentity();
        if (!isset($_user['id']) ||
                (!isset($_user['login']) and empty($_user['login'])))
            $this->loginAction();

        $um = new UsersManager($this->di->get('db'));
        $info_user = $um->getUser((int) $_user['id']);

        $userName = (strlen($info_user->getName())) ? $info_user->getName() : ' ';

        if ($this->getRequest()->hasPost('type', 'message')) { // 'email', 'name',
            try {
                $ticketId = $supportModel->submitTicket(
                        $userName, $_user['login'], $this->getRequest()->post('type'), htmlspecialchars(strip_tags(trim($this->getRequest()->post('message'))))
                ); // $this->getRequest()->post('email'),  $this->getRequest()->post('name'), 

                $this->di->get('usersNotesProcessor')->supportTicketSent($ticketId);
                
                $this->di['eventManager']->emit('cp-support-completed', array(
                    'userId' => $this->auth['id']
                ));

                $this->view->success = true;

                // Your ticket #%1$s has been successfully sent!<br/> Our Support Team will contact you within 1 business day.
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Ticket #%1$s has been successfully sent. Our support representative will contact you via email as soon as possible.', array('ticketId' => $ticketId)));
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

        $this->view->additionalMessage = $supportModel->getAdditionalMessage();
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
                
                \CS\Users\UsersManager::registerListeners($this->di['db']);
                $this->di['eventManager']->emit('cp-lost-password-completed', array(
                    'email' => $email
                ));
                
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

            $this->di['freeTrialLinks']->setAccessCookie(FreeTrialLinks::HIDDEN);

            $deviceId = $this->getRequest()->get('device');

            if ($deviceId !== null) {
                $devicesModel = new \Models\Devices($this->di);
                $devicesModel->setCurrentDevId($deviceId);
                $this->redirect($this->di['router']->getRouteUrl('calls'));
            } else {
                $this->redirect($this->di['router']->getRouteUrl('main'));
            }
        }

        $this->error404();
    }

    public function pagePrev()
    {
        $previous = $this->di['config']['domain']; // "javascript:history.go(-1)";
        if (isset($_SERVER['HTTP_REFERER'])) {
            $previous = $_SERVER['HTTP_REFERER'];
        }
        return $previous;
    }

    public function tosAction()
    {
        $content = new Content($this->di);
        $legalInfo =  $content->getLegalInfo('tos');

        if ($this->getRequest()->isPost()){
            $content->saveUserAcceptance($this->auth['id'], $legalInfo['legal_id'], $legalInfo['id'], 'tos');
            $this->loginRedirect();
        }

        $this->view->text = $legalInfo['text'];
        $this->view->policyName = 'terms of service';
        $this->view->acceptUpdate = 'I agree to the updated Legal Policies';
        $this->view->title = 'Notification: Changes in Pumpic Legal Policy';
        $this->setView('legal/layout.html');
    }
    public function policyAction()
    {
        $content = new Content($this->di);
        $legalInfo =  $content->getLegalInfo('policy');

        if ($this->getRequest()->isPost()){
            $content->saveUserAcceptance($this->auth['id'], $legalInfo['legal_id'], $legalInfo['id'], 'policy');
            $this->loginRedirect();
        }

        $this->view->text = $legalInfo['text'];
        $this->view->policyName = 'privacy policy';
        $this->view->title = 'Notification: Changes in Pumpic Legal Policy';
        $this->view->acceptUpdate = 'I agree to the updated Legal Policies';


        $this->setView('legal/layout.html');
    }
}
