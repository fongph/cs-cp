<?php

namespace Controllers;

use CS\Devices\DeviceObserver;
use CS\Devices\InvalidLimitationsCountException;
use CS\Models\Device\DeviceRecord;
use CS\Models\License\LicenseNotFoundException;
use CS\Models\License\LicenseRecord;
use CS\Queue\Manager as QueueManager;
use CS\Users\UsersNotes;
use Monolog\Logger;
use Models\Billing;
use Components\CloudDeviceState;
use Components\CloudDeviceManager;
use Components\CloudDeviceManager\AbstractCloudDeviceManager;
use Models\Devices,
    System\FlashMessages,
    CS\Users\UsersManager,
    CS\Queue\BackupQueueUnit,
    CS\ICloud\AuthorizationException,
    CS\Users\InvalidPasswordException,
    CS\Users\PasswordsNotEqualException,
    CS\Users\PasswordTooShortException,
    CS\Models\Device\DeviceICloudRecord,
    CS\Models\Device\DeviceNotFoundException,
    CS\ICloud\Backup as ICloudBackup;

class Profile extends BaseController {

    private $deviceRecord, $oldLicenseRecord, $newLicenseRecord;

    public function indexAction()
    {

        if ($this->getRequest()->isPost()) {
            $this->checkDemo($this->di['router']->getRouteUrl('profile'));
            $this->checkSupportMode();

            if ($this->getRequest()->post('settings') !== null) {
                $this->processSettings();
            } else if ($this->getRequest()->post('changePassword') !== null) {
                $this->processChangePassword();
            }
        }

        $usersModel = new \Models\Users($this->di);

        if ($this->di->get('isWizardEnabled')) {
            $deviceManager = new Devices($this->di);
            $this->view->availabledevices = $deviceManager->getUserDevices($this->auth['id']);
        } else {
            $this->view->availabledevices = false;
        }

        $this->view->title = $this->di->getTranslator()->_('Your Profile');
        $this->view->recordsPerPage = $this->auth['records_per_page'];
        $this->view->recordsPerPageList = $usersModel->getRecordsPerPageList();
        $this->view->subscribes = $usersModel->getSubscribes($this->auth['id']);

        $this->setView('profile/index.htm');
    }

    public function assignChoiceAction()
    {
        $this->checkDemo($this->di['router']->getRouteUrl('profile'));
        $this->checkSupportMode();

        $billingModel = new Billing($this->di);

        $this->view->deviceRecord = $this->getDeviceRecord();
        if ($this->getRequest()->get('oldLicenseId')) {
            $this->view->title = $this->di->getTranslator()->_('Re-Assign Subscription');
            $this->view->oldLicenseRecord = $this->getOldLicenseRecord();
        } else {
            $this->view->title = $this->di->getTranslator()->_('Assign Subscription');
            $this->view->oldLicenseRecord = false;
        }
        $this->view->avilable = array(
            'icloud' => array('premium', 'premium-double', 'trial', 'ios-icloud', 'ios-icloud-double'),
            'ios' => array('premium', 'premium-double', 'basic', 'basic-double', 'trial', 'ios-jailbreak', 'ios-jailbreak-double'),
            'android' => array('premium', 'premium-double', 'basic', 'basic-double', 'trial', 'android-basic', 'android-basic-double', 'android-premium', 'android-premium-double')
        );
        $this->view->packages = $billingModel->getAvailablePackages($this->auth['id']);
        $this->setView('profile/assignSubscriptions.htm');
    }

    public function upgradeConfirmAction()
    {
        $this->checkDemo($this->di['router']->getRouteUrl('profile'));
        $this->checkSupportMode();

        $this->view->deviceRecord = $this->getDeviceRecord();
        $this->view->licenseRecord = $this->getNewLicenseRecord();
        $this->view->oldLicenseRecord = $this->getOldLicenseRecord();
        $this->view->title = $this->di->getTranslator()->_('Re-Assign Subscription');
        $this->setView('profile/confirmUpgrade.htm');
    }

    public function assignProcessAction()
    {
        $this->checkDemo($this->di['router']->getRouteUrl('profile'));
        $this->checkSupportMode();

        if ($this->getRequest()->isPost()) {
            try {
                /** @var \CS\Users\UsersNotes $userNotes */
                $userNotes = $this->di->get('usersNotesProcessor');
                $deviceObserver = new DeviceObserver($this->di->get('logger'));
                $deviceObserver
                        ->setMainDb($this->di->get('db'))
                        ->setDevice($this->getDeviceRecord())
                        ->setLicense($this->getNewLicenseRecord());

                if ($this->getRequest()->hasGet('oldLicenseId')) {
                    $oldLicenseId = $this->getOldLicenseRecord()->getId();

                    $deviceObserver
                            ->setBeforeSave(function() {
                                $this->di['devicesManager']->closeDeviceLicenses($this->getDeviceRecord()->getId(), false);
                                return true;
                            })->setAfterSave(function() use ($deviceObserver, $userNotes, $oldLicenseId) {
                        $userNotes->licenseUpgraded($deviceObserver->getDevice()->getId(), $oldLicenseId, $deviceObserver->getLicense()->getId());
                        $this->di->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di->getTranslator()->_('Subscription has been upgraded'));

                        $eventManager = \EventManager\EventManager::getInstance();
                        $eventManager->emit('license-assigned', array(
                            'userId' => $deviceObserver->getLicense()->getUserId(),
                            'deviceId' => $deviceObserver->getDevice()->getId(),
                            'licenseId' => $deviceObserver->getLicense()->getId()
                        ));
                    });
                } else {
                    $deviceObserver->setAfterSave(function() use($deviceObserver, $userNotes) {
                        $userNotes->licenseAssigned($deviceObserver->getLicense()->getId(), $deviceObserver->getDevice()->getId());
                        if ($this->getDeviceRecord()->getOS() === DeviceRecord::OS_ICLOUD) {
                            $queueManage = new QueueManager($this->di->get('queueClient'));
                            $queueManage->addTaskDevice('downloadChannel', $this->getDeviceRecord()->getICloudDevice());
                        }

                        $eventManager = \EventManager\EventManager::getInstance();
                        $eventManager->emit('license-assigned', array(
                            'userId' => $deviceObserver->getLicense()->getUserId(),
                            'deviceId' => $deviceObserver->getDevice()->getId(),
                            'licenseId' => $deviceObserver->getLicense()->getId()
                        ));

                        $this->di->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di->getTranslator()->_('Subscription has been assigned to your device'));
                    });
                }
                $deviceObserver->assignLicenseToDevice();
            } catch (InvalidLimitationsCountException $e) {
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Device already has subscription'));
            } catch (\Exception $e) {
                /** @var Logger $logger */
                $logger = $this->di->get('logger');
                $logger->addCritical($e);
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Internal Server Error'));
            }
        }
        $this->redirect($this->di->getRouter()->getRouteUri('profile'));
    }

    private function getDeviceRecord()
    {
        if (is_null($this->deviceRecord)) {
            try {
                if (is_null($this->getRequest()->get('deviceId')))
                    throw new DeviceNotFoundException;

                $deviceRecord = new DeviceRecord($this->di->get('db'));
                $deviceRecord->load($this->getRequest()->get('deviceId'));
                if ($deviceRecord->getUserId() !== $this->auth['id'])
                    throw new DeviceNotFoundException;

                $this->deviceRecord = $deviceRecord;
            } catch (DeviceNotFoundException $e) {
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Device Not Found'));
                $this->redirect($this->di->getRouter()->getRouteUri('profile'));
            }
        }

        return $this->deviceRecord;
    }

    private function getOldLicenseRecord()
    {
        if (is_null($this->oldLicenseRecord)) {
            try {
                if (is_null($this->getRequest()->get('oldLicenseId')))
                    throw new LicenseNotFoundException;

                $oldLicenseRecord = new LicenseRecord($this->di->get('db'));
                $oldLicenseRecord->load($this->getRequest()->get('oldLicenseId'));
                if ($oldLicenseRecord->getUserId() !== $this->auth['id'] || $oldLicenseRecord->getDeviceId() != $this->getDeviceRecord()->getId() || $oldLicenseRecord->getStatus() !== $oldLicenseRecord::STATUS_ACTIVE)
                    throw new LicenseNotFoundException;

                $this->oldLicenseRecord = $oldLicenseRecord;
            } catch (LicenseNotFoundException $e) {
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Subscription Not Found'));
                $this->redirect($this->di->getRouter()->getRouteUri('profile'));
            }
        }
        return $this->oldLicenseRecord;
    }

    private function getNewLicenseRecord()
    {
        if (is_null($this->newLicenseRecord)) {
            try {
                if (is_null($this->getRequest()->get('licenseId')))
                    throw new LicenseNotFoundException;

                $newLicenseRecord = new LicenseRecord($this->di->get('db'));
                $newLicenseRecord->load($this->getRequest()->get('licenseId'));
                if ($newLicenseRecord->getStatus() !== $newLicenseRecord::STATUS_AVAILABLE || $newLicenseRecord->getUserId() !== $this->auth['id'])
                    throw new LicenseNotFoundException;

                if ($this->getDeviceRecord()->getOS() == DeviceRecord::OS_ICLOUD && $newLicenseRecord->getProduct()->getGroup() != 'premium' && $newLicenseRecord->getProduct()->getGroup() != 'premium-double' && $newLicenseRecord->getProduct()->getGroup() != 'ios-icloud' && $newLicenseRecord->getProduct()->getGroup() != 'ios-icloud-double' && $newLicenseRecord->getProduct()->getGroup() != 'trial') {
                    $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('iCloud solution is available for Premium Subscription only'));

                    if ($this->getRequest()->hasGet('oldLicenseId')) {
                        $this->redirect("{$this->di->getRouter()->getRouteUri('profileAssignChoice')}?deviceId={$this->getDeviceRecord()->getId()}&oldLicenseId={$this->getOldLicenseRecord()->getId()}");
                    } else
                        $this->redirect("{$this->di->getRouter()->getRouteUri('profileAssignChoice')}?deviceId={$this->getDeviceRecord()->getId()}");
                }

                $this->newLicenseRecord = $newLicenseRecord;
            } catch (LicenseNotFoundException $e) {
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Subscription Not Found'));
                $this->redirect($this->di->getRouter()->getRouteUri('profile'));
            }
        }
        return $this->newLicenseRecord;
    }

    private function processSettings()
    {
        $usersModel = new \Models\Users($this->di);

        $settings = array(
            'subscribes' => []
        );

        if ($this->getRequest()->hasPost('subscribes')) {
            $settings['subscribes'] = array_keys($this->getRequest()->post('subscribes'));
        }

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
                        $this->auth['id'], $this->getRequest()->post('oldPassword'), $this->getRequest()->post('newPassword'), $this->getRequest()->post('newPassword2')
                );

                $this->di->get('usersNotesProcessor')->accountCustomPasswordSaved();

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

    private function updateCloudCredentials(DeviceICloudRecord $cloudRecord, AbstractCloudDeviceManager $cloudDeviceManager)
    {
        $state = $cloudDeviceManager->getState();

        $cloudRecord->setApplePassword($state->getApplePassword())
                ->setLastError(0)
                ->setTwoFactorAuthenticationEnabled($state->getTwoFactorAuthEnabled() ? 1 : 0)
                ->save();

        $locations = new \Models\Cp\Locations($this->di);
        $locations->setFmipDisabled($cloudRecord->getDevId(), false);

        $this->di->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di->getTranslator()->_('iCloud account has been successfully validated. A new backup check will be performed shortly, and if new monitoring data is available, it will be displayed in Control Panel within several hours.'));
        $this->redirect($this->di->getRouter()->getRouteUri('profile'));
    }

    public function changeICloudPasswordAction()
    {
        $logger = $this->di->get('logger');

        $this->checkDemo($this->di['router']->getRouteUrl('profile'));
        $this->checkSupportMode();

        $iCloudRecord = new DeviceICloudRecord($this->di->get('db'));
        $iCloudRecord->loadByDevId($this->params['deviceId']);

        $deviceRecord = $iCloudRecord->getDeviceRecord();
        if ($deviceRecord->getUserId() !== $this->auth['id'] || $deviceRecord->getDeleted()) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Device Not Found'));
            $this->redirect($this->di->getRouter()->getRouteUri('profile'));
        }

        $cloudDeviceManager = $this->di['cloudDeviceManager'];

        if ($this->getRequest()->hasPost('token')) {
            $token = $cloudDeviceManager->decryptState($this->getRequest()->post('token'));
            $cloudDeviceManager->setState($token);

            if ($this->getRequest()->hasPost('token', 'verificationCode')) {
                $logger->addInfo('iCloud password change USER #' . $this->auth['id'] . ' DEVICE: ' . $iCloudRecord->getDevId() . ' ' . $cloudDeviceManager->getState()->getApplePassword() . ' ' . $this->getRequest()->post('verificationCode'));
            } elseif ($this->getRequest()->hasPost('password') && strlen($this->getRequest()->post('password')) > 0) {
                $password = $this->getRequest()->post('password');
                $cloudDeviceManager->getState()->setApplePassword($password);

                $logger->addInfo('iCloud password change USER #' . $this->auth['id'] . ' DEVICE: ' . $iCloudRecord->getDevId() . ' ' . $password);
            }
        } else {
            $cloudDeviceManager->getState()
                    ->setAction(CloudDeviceState::ACTION_AUTHENTICATE)
                    ->setAppleId($iCloudRecord->getAppleId());
        }

        if ($this->getRequest()->isPost()) {
            try {
                $state = $cloudDeviceManager->getState();

                switch ($state->getAction()) {
                    case CloudDeviceState::ACTION_AUTHENTICATE:
                        return $this->cloudAuthenticate($cloudDeviceManager, $iCloudRecord);

                    case CloudDeviceState::ACTION_SUBMIT_TWO_FACTOR_AUTH_CHALLENGE:
                        return $this->cloudSecondFactorAuthenticate($cloudDeviceManager, $iCloudRecord);
                }
            } catch (CloudDeviceManager\Exception\BadCredentialsException $e) {
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_("The password you have entered doesn’t match Apple ID. Check the entry and try again."));
                $this->redirect($this->di->getRouter()->getRouteUrl('profileICloudPasswordReset', ['deviceId' => $this->params['deviceId']]));
            } catch (CloudDeviceManager\Exception\AccountLockedException $e) {
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_("Your iCloud account has been locked."));
                $this->redirect($this->di->getRouter()->getRouteUrl('profileICloudPasswordReset', ['deviceId' => $this->params['deviceId']]));
            } catch (\Exception $e) {
                $logger->addCritical($e);
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Unexpected Error. Please try later or contact us!'));
                $this->redirect($this->di->getRouter()->getRouteUrl('profileICloudPasswordReset', ['deviceId' => $this->params['deviceId']]));
            }
        }

        $this->view->title = $this->di->getTranslator()->_('Validate target iCloud account in our system');
        $this->view->token = $cloudDeviceManager->encryptState($cloudDeviceManager->getState());
        $this->view->appleId = $cloudDeviceManager->getState()->getAppleId();
        $this->setView('profile/changeICloudPassword.auth.htm');
    }

    private function cloudAuthenticate(AbstractCloudDeviceManager $cloudDeviceManager, DeviceICloudRecord $cloudRecord)
    {
        try {
            $cloudDeviceManager->authenticate();

            return $this->updateCloudCredentials($cloudRecord, $cloudDeviceManager);
        } catch (CloudDeviceManager\Exception\TwoFactorAuthenticationRequiredException $e) {
            $state = $cloudDeviceManager->getState();

            $logger = $this->di->get('logger');
            $logger->addInfo('iCloud 2FA for USER #' . $this->auth['id'] . ' ACCOUNT: ' . $state->getAppleId() . ' ' . $state->getApplePassword());

            $state->setAction(CloudDeviceState::ACTION_SUBMIT_TWO_FACTOR_AUTH_CHALLENGE);

            return $this->cloudSecondFactorAuthenticate($cloudDeviceManager, $cloudRecord);
        }
    }

    public function cloudSecondFactorAuthenticate(AbstractCloudDeviceManager $cloudDeviceManager, DeviceICloudRecord $cloudRecord)
    {
        $this->view->invalidVerificationCode = false;

        if ($this->getRequest()->hasPost('verificationCode')) {
            $code = $this->getRequest()->post('verificationCode');

            try {
                $cloudDeviceManager->submitTwoFactorAuth($code);

                return $this->updateCloudCredentials($cloudRecord, $cloudDeviceManager);
            } catch (CloudDeviceManager\Exception\InvalidVerificationCodeException $e) {
                $this->view->invalidVerificationCode = true;
            }
        } else {
            $cloudDeviceManager->performTwoFactorAuth();
        }

        $this->view->title = $this->di->getTranslator()->_('Connect to iCloud Account');
        $this->view->appleId = $cloudDeviceManager->getState()->getAppleId();
        $this->view->token = $cloudDeviceManager->encryptState($cloudDeviceManager->getState());
        $this->setView('profile/changeICloudPassword.2fa.htm');
    }

    public function mailUnsubscribeAction()
    {
        $usersModel = new \Models\Users($this->di);

        if (!in_array($this->params['type'], $usersModel->getMailTypes())) {
            $this->error404();
        }

        if (!$this->getDI()->getAuth()->hasIdentity()) {
            if ($this->params['type'] == 'system') {
                $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, "You are about to unsubscribe from account status emails. Please, enter your login and password to confirm that you no longer want to receive them.");
            } elseif ($this->params['type'] == 'monitoring') {
                $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, "You are about to unsubscribe from monitoring notification emails. Please, enter your login and password to confirm that you no longer want to receive them.");
            }

            $this->redirect($this->getDI()->getRouter()->getRouteUrl('main') . '?redirect=' . rawurlencode($this->getDI()->getRequest()->uri()));
        }

        $optionKey = 'mail-type-' . $this->params['type'] . '-unsubscribed';

        $usersManager = $this->di['usersManager'];
        $usersManager->setUserOption($this->auth['id'], $optionKey, 1, \CS\Models\User\Options\UserOptionRecord::SCOPE_MAILING);

        if ($this->params['type'] == 'system') {
            $this->getDI()->getFlashMessages()->add(FlashMessages::SUCCESS, "You have been unsubscribed. If you opt to receive account status emails again, you can reactivate subscription on this page.");
        } elseif ($this->params['type'] == 'monitoring') {
            $this->getDI()->getFlashMessages()->add(FlashMessages::SUCCESS, "You have been unsubscribed. If you opt to receive monitoring notification emails again, you can reactivate subscription on this page.");
        }

        $this->redirect($this->di['router']->getRouteUrl('profile'));
    }

    public function ajaxResponse($status, $data = null)
    {
        $this->makeJSONResponse(array(
            'status' => (bool) $status,
            'data' => $data,
        ));
    }

}
