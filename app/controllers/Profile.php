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

    private function updateCloudCredentials(DeviceICloudRecord $cloudRecord, $password, $token, $isTwoFactor)
    {
        $cloudRecord->setApplePassword($password);
        if ($cloudRecord->getLastError() == DeviceICloudRecord::ERROR_AUTHENTICATION || 
                $cloudRecord->getLastError() == DeviceICloudRecord::ERROR_ACCOUNT_LOCKED) {
            $cloudRecord->setLastError(DeviceICloudRecord::ERROR_NONE);
        }

        $cloudRecord->setTwoFactorAuthenticationEnabled($isTwoFactor ? 1 : 0)
                ->setTokenGenerationTime(time())
                ->save();

        $cloudRecord->getDeviceRecord()
                ->setToken($token)
                ->save();

        $queueManager = new \CS\Queue\Manager($this->di['queueClient']);
        $iCloudDevice = new DeviceICloudRecord($this->di->get('db'));

        $iCloudDevice->loadByDevId($cloudRecord->getDevId());

        if ($queueManager->addTaskDevice('downloadChannel', $iCloudDevice)) {
            $iCloudDevice->setProcessing(1);
        } else {
            $iCloudDevice->setLastError($queueManager->getError());
        }

        $iCloudDevice->save();
        
        $locations = new \Models\Cp\Locations($this->di);
        $locations->setFmipDisabled($this->di['devId'], true);        

        $this->di->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di->getTranslator()->_('iCloud account has been successfully validated. A new backup check will be performed shortly, and if new monitoring data is available, it will be displayed in Control Panel within several hours.'));
        $this->redirect($this->di->getRouter()->getRouteUri('profile'));
    }

    public function changeICloudPasswordAction()
    {
        $logger = $this->di->get('logger');

        $this->checkDemo($this->di['router']->getRouteUrl('profile'));
        $this->checkSupportMode();

        $this->view->twoFactorAuthentication = false;
        $this->view->invalidVerificationCode = false;

        $iCloudRecord = new DeviceICloudRecord($this->di->get('db'));
        $iCloudRecord->loadByDevId($this->params['deviceId']);

        $deviceRecord = $iCloudRecord->getDeviceRecord();
        if ($deviceRecord->getUserId() !== $this->auth['id'] || $deviceRecord->getDeleted()) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Device Not Found'));
            $this->redirect($this->di->getRouter()->getRouteUri('profile'));
        }

        try {
            $this->view->title = $this->di->getTranslator()->_('Validate target iCloud account in our system');
            $this->view->iCloud = $iCloudRecord;

            if ($this->getRequest()->hasPost('password', 'verificationCode')) {
                $logger->addInfo('iCloud password change USER #' . $this->auth['id'] . ' DEVICE: ' . $this->params['deviceId'] . ' ' . $this->getRequest()->post('password') . ' ' . $this->getRequest()->post('verificationCode'));

                $client = new \AppleCloud\ServiceClient\Setup($logger);

                $auth = $client->authenticate(
                        $iCloudRecord->getAppleId(), $this->getRequest()->post('password'), $this->getRequest()->post('verificationCode')
                );

                $this->updateCloudCredentials($iCloudRecord, $this->getRequest()->post('password'), $auth->getFullToken(), true);
            } elseif ($this->getRequest()->isPost() && strlen($this->getRequest()->post('password')) > 0) {
                $logger->addInfo('iCloud password change USER #' . $this->auth['id'] . ' DEVICE: ' . $this->params['deviceId'] . ' ' . $this->getRequest()->post('password'));

                $client = new \AppleCloud\ServiceClient\Setup($logger);
                $auth = $client->authenticate($iCloudRecord->getAppleId(), $this->getRequest()->post('password'));

                $this->updateCloudCredentials($iCloudRecord, $this->getRequest()->post('password'), $auth->getFullToken(), false);
            }
        } catch (\AppleCloud\ServiceClient\Exception\BadVerificationCredentialsException $e) {
            $this->view->twoFactorAuthentication = true;
            $this->view->invalidVerificationCode = true;
        } catch (\AppleCloud\ServiceClient\Exception\BadCredentialsException $e) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_("The password you have entered doesn’t match Apple ID. Check the entry and try again."));
            $this->redirect($this->di->getRouter()->getRouteUrl('profileICloudPasswordReset', ['deviceId' => $this->params['deviceId']]));
        } catch (\AppleCloud\ServiceClient\Exception\TwoStepVerificationException $e) {
            $this->view->twoFactorAuthentication = true;
        }

        $this->setView('profile/changeICloudPassword.htm');
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
