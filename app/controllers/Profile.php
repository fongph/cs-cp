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

class Profile extends BaseController
{

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
        } else
            $this->view->availabledevices = false;

        $this->view->title = $this->di->getTranslator()->_('Your Profile');
        $this->view->recordsPerPage = $this->auth['records_per_page'];
        $this->view->recordsPerPageList = $usersModel->getRecordsPerPageList();
        $this->setView('profile/index.htm');
    }
    
    public function assignChoiceAction()
    {
        $this->checkDemo($this->di['router']->getRouteUrl('profile'));
        $this->checkSupportMode();
        
        $billingModel = new Billing($this->di);
        
        $this->view->deviceRecord = $this->getDeviceRecord();
        if($this->getRequest()->get('oldLicenseId')) {
            $this->view->title = $this->di->getTranslator()->_('Upgrade Subscription');
            $this->view->oldLicenseRecord = $this->getOldLicenseRecord();
        } else {
            $this->view->title = $this->di->getTranslator()->_('Assign Subscription');
            $this->view->oldLicenseRecord = false;
        }
       
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
        $this->view->title = $this->di->getTranslator()->_('Upgrade Subscription');
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
                        if($this->getDeviceRecord()->getOS() === DeviceRecord::OS_ICLOUD){
                            $queueManage = new QueueManager($this->di->get('queueClient'));
                            $queueManage->addTaskDevice('downloadChannel-priority', $this->getDeviceRecord()->getICloudDevice());
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
        if(is_null($this->deviceRecord)){
            try {
                if(is_null($this->getRequest()->get('deviceId')))
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
        if(is_null($this->oldLicenseRecord)) {
            try {
                if(is_null($this->getRequest()->get('oldLicenseId')))
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
        if(is_null($this->newLicenseRecord)){
            try {
                if(is_null($this->getRequest()->get('licenseId')))
                    throw new LicenseNotFoundException;

                $newLicenseRecord = new LicenseRecord($this->di->get('db'));
                $newLicenseRecord->load($this->getRequest()->get('licenseId'));
                if ($newLicenseRecord->getStatus() !== $newLicenseRecord::STATUS_AVAILABLE || $newLicenseRecord->getUserId() !== $this->auth['id'])
                    throw new LicenseNotFoundException;

                if ($this->getDeviceRecord()->getOS() == DeviceRecord::OS_ICLOUD && $newLicenseRecord->getProduct()->getGroup() != 'premium' && $newLicenseRecord->getProduct()->getGroup() != 'trial') {
                    $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('iCloud solution is available for Premium Subscription only'));
                    
                    if($this->getRequest()->hasGet('oldLicenseId')){
                        $this->redirect("{$this->di->getRouter()->getRouteUri('profileAssignChoice')}?deviceId={$this->getDeviceRecord()->getId()}&oldLicenseId={$this->getOldLicenseRecord()->getId()}");
                    
                    } else $this->redirect("{$this->di->getRouter()->getRouteUri('profileAssignChoice')}?deviceId={$this->getDeviceRecord()->getId()}");
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

    public function changeICloudPasswordAction()
    {
        $this->checkDemo($this->di['router']->getRouteUrl('profile'));
        $this->checkSupportMode();
        
        try {
            if($this->getRequest()->hasGet('deviceId')){

                $iCloudRecord = new DeviceICloudRecord($this->di->get('db'));
                $iCloudRecord->loadByDevId($this->getRequest()->get('deviceId'));
                
                $deviceRecord = $iCloudRecord->getDeviceRecord();
                if($deviceRecord->getUserId() !== $this->auth['id'] || $deviceRecord->getDeleted())
                    throw new DeviceNotFoundException;
                
                if ($this->getRequest()->isAjax() && $this->getRequest()->hasPost('newPassword')) {

                    //todo check auth count
                    $iCloud = new ICloudBackup($iCloudRecord->getAppleId(), $this->getRequest()->post('newPassword'));

                    $iCloudRecord->setApplePassword($this->getRequest()->post('newPassword'));
                    if($iCloudRecord->getLastError() == DeviceICloudRecord::ERROR_AUTHENTICATION)
                        $iCloudRecord->setLastError(DeviceICloudRecord::ERROR_NONE);
                    $iCloudRecord->save();

                    $this->di->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di->getTranslator()->_('You have successfully updated the iCloud password. A new iCloud backup will be uploaded shortly'));
                    $this->ajaxResponse(true, array(
                        'location' => $this->di->getRouter()->getRouteUri('profile')
                    ));
                }
                $this->view->title = $this->di->getTranslator()->_('Change iCloud Password');
                $this->view->iCloud = $iCloudRecord;
                $this->setView('profile/changeICloudPassword.htm');
                
            } else throw new DeviceNotFoundException;
            
        } catch (DeviceNotFoundException $e){
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Device Not Found'));
            $this->redirect($this->di->getRouter()->getRouteUri('profile'));
            
        } catch (\CS\ICloud\InvalidAuthException $e) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_("Oops, the iCloud password didn't work. Please try again"));
            $this->ajaxResponse(false, array(
                'location' => $this->di->getRouter()->getRouteUri('profileICloudPasswordReset')."?deviceId={$this->getRequest()->get('deviceId')}"
            ));
        }
    }

    public function ajaxResponse($status, $data = null)
    {
        $this->makeJSONResponse(array(
            'status' => (bool) $status,
            'data' => $data,
        ));
    }
    
}
