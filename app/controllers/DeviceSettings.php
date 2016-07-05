<?php

namespace Controllers;

use CS\Models\Device\DeviceICloudRecord;
use CS\Models\Device\DeviceRecord;
use CS\Settings\GlobalSettings;
use System\FlashMessages,
    Models\Modules;

class DeviceSettings extends BaseModuleController
{

    protected $module = Modules::SETTINGS;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $settingsModel = new \Models\Cp\Settings($this->di);
        $infoModel = new \Models\Cp\Info($this->di);

        if ($this->getRequest()->isPost()) {
            $this->checkDemo($this->di['router']->getRouteUrl('settings'));

            if ($this->getRequest()->hasPost('phonesBlackList', 'phone')) {
                try {
                    if ($settingsModel->addBlackListPhone($this->di['devId'], htmlspecialchars($this->getRequest()->post('phone')))) {
                        $this->makeJSONResponse([
                            'status' => 201,
                            'message' => $this->di['t']->_('The phone number has been successfully added!')
                        ]);
                    } else {
                        $this->makeJSONResponse([
                            'status' => 422,
                            'message' => $this->di['t']->_('Error occurred during adding the phone number!')
                        ]);
                    }
                } catch (\Models\Cp\Settings\InvalidPhoneNumberException $e) {
                    $this->makeJSONResponse([
                        'status' => 422,
                        'message' => $this->di['t']->_('Invalid phone number!')
                    ]);
                } catch (\Models\Cp\Settings\PhoneNumberExistException $e) {
                    $this->makeJSONResponse([
                        'status' => 422,
                        'message' => $this->di['t']->_('The phone number already exists on the list!')
                    ]);
                }
            } else if ($this->getRequest()->hasPost('wordsBlackList', 'badWord')) {
                try {
                    if ($settingsModel->addBadWord($this->di['devId'], $this->getRequest()->post('badWord'))) {
                        $this->makeJSONResponse([
                            'status' => 201,
                            'message' => $this->di['t']->_('The bad word has been successfully added!')
                        ]);
                    } else {
                        $this->makeJSONResponse([
                            'status' => 422,
                            'message' => $this->di['t']->_('Error occurred during adding the bad word!')
                        ]);
                    }
                } catch (\Models\Cp\Settings\InvalidBadWordException $e) {
                    $this->makeJSONResponse([
                        'status' => 422,
                        'message' => $this->di['t']->_('Invalid word!')
                    ]);
                } catch (\Models\Cp\Settings\BadWordExistException $e) {
                    $this->makeJSONResponse([
                        'status' => 422,
                        'message' => $this->di['t']->_('The bad word already exists on the list!')
                    ]);
                }

                $this->makeJSONResponse([
                    'status' => 500,
                    'message' => $this->di['t']->_('Something went wrong')
                ]);
            } else if ($this->getRequest()->hasPost('simNotifications')) {
                $simNotifications = $this->getRequest()->post('simNotifications');
                $simNotifications = $simNotifications == 'true' ? 1 : 0;

                if ($settingsModel->setSimChangeNotifications($this->di['devId'], $simNotifications)) {
                    $this->makeJSONResponse([
                        'status' => 200,
                        'message' => $this->di['t']->_('Sim change notifications changed!')
                    ]);
                }

                $this->makeJSONResponse([
                    'status' => 422,
                    'message' => $this->di['t']->_('Sim change notifications doesn\'t changed!')
                ]);
            } else if ($this->getRequest()->hasPost('deviceSettings', 'name')) {

                $devicesModel = new \Models\Devices($this->di);
                try {
                    $devicesModel->setDeviceName($this->di['devId'], $this->getRequest()->post('name'));

                    $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The settings have been successfully updated!'));
                } catch (\Models\Devices\InvalidDeviceNameException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The device name must be between 1 and 32 characters long!'));
                }
            } else if ($this->getRequest()->hasPost('lockDevice', 'password')) {
                try {
                    $settingsModel->lockDevice($this->di['devId'], $this->getRequest()->post('password'));

                    $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The password has been successfully added!'));
                } catch (\Models\Cp\Settings\InvalidPasswordException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid device password!'));
                }
            } else if ($this->getRequest()->hasPost('smsSettings')) {
                $this->smsSettings();
            } else if ($this->getRequest()->hasGet('delete')) {
                $this->deleteDevice();
            }
            $this->redirect($this->di['router']->getRouteUrl('settings'));
        } else if ($this->getRequest()->hasGet('removePhonesBlackList')) {
            $this->checkDemo($this->di['router']->getRouteUrl('settings'));

            try {
                $settingsModel->removeBlackListPhone($this->di['devId'], $this->getRequest()->get('removePhonesBlackList'));
                $this->makeJSONResponse([
                    'status' => 200,
                    'message' => $this->di['t']->_('The phone number has been successfully deleted!')
                ]);
            } catch (\Models\Cp\Settings\PhoneNumberNotFoundInListException $e) {
                $this->makeJSONResponse([
                    'status' => 422,
                    'message' => $this->di['t']->_('The phone is not on the list!')
                ]);
            }

            $this->makeJSONResponse([
                'status' => 500,
                'message' => $this->di['t']->_('Something went wrong')
            ]);
        } else if ($this->getRequest()->hasGet('removeWordsBlackList')) {
            $this->checkDemo($this->di['router']->getRouteUrl('settings'));

            try {
                $settingsModel->removeBlackListWord($this->di['devId'], $this->getRequest()->get('removeWordsBlackList'));
                $this->makeJSONResponse([
                    'status' => 200,
                    'message' => $this->di['t']->_('The word has been successfully deleted!')
                ]);
            } catch (\Models\Cp\Settings\BadWordNotFoundInListException $e) {
                $this->makeJSONResponse([
                    'status' => 422,
                    'message' => $this->di['t']->_('The word is not on the list!')
                ]);
            }

            $this->makeJSONResponse([
                'status' => 500,
                'message' => $this->di['t']->_('Something went wrong')
            ]);
        } else if ($this->getRequest()->hasGet('rebootDevice')) {
            $this->checkDemo($this->di['router']->getRouteUrl('settings'));

            $settingsModel->setRebootDevice($this->di['devId']);

            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Request to reboot device has been successfully sent!'));

            $this->redirect($this->di['router']->getRouteUrl('settings'));
        } else if ($this->getRequest()->hasGet('rebootApp')) {
            $this->checkDemo($this->di['router']->getRouteUrl('settings'));

            $settingsModel->setRebootApp($this->di['devId']);

            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Request to reboot application has been successfully sent to device!'));

            $this->redirect($this->di['router']->getRouteUrl('settings'));
        } else if ($this->getRequest()->hasGet('delete')) {
            $this->checkDemo($this->di->getRouter()->getRouteUrl('settings'));
            $this->view->currentDevice = $this->di->get('currentDevice');
            $this->setView('cp/settings-delete.htm');
            return;
        } else if ($this->getRequest()->hasGet('check-new-backup')) {
            $this->checkNewBackup();
            $this->redirect($this->di['router']->getRouteUrl('settings'));
        }

        $this->view->currentDevice = $this->di->get('currentDevice');
        $this->view->data = $settingsModel->getSettings($this->di['devId']);

        //d($this->view->data);

        $this->view->osVersion = $this->di->get('currentDevice')['os_version'];
        if ($this->di->get('currentDevice')['os'] == 'android') {
            $exploded = explode('_', $this->di->get('currentDevice')['os_version']);
            if (count($exploded) == 2) {
                $this->view->osVersion = $exploded[1];
            }
        }

        $this->view->hasPackage = ($this->di['currentDevice']['package_name'] !== null);
//        var_dump($this->view->hasPackage, $this->di->get('currentDevice'), $settingsModel->getSettings($this->di['devId']));die;
        if ($this->di->get('currentDevice')['os'] != 'icloud') {
            $this->view->appLastVersion = GlobalSettings::getVersionApp($this->di->get('currentDevice')['os']);
        }

        if ($this->view->currentDevice['os'] == 'icloud') {
            try {
                $this->view->iCloudRecord = new DeviceICloudRecord($this->di->get('db'));
                $this->view->iCloudRecord->loadByDevId($this->di->get('devId'));
            } catch (\Exception $e) {
                // ignore...
            }
        }

        $this->setView('cp/settings.htm');
    }

    protected function smsSettings()
    {
        $outgoingLimitation = $this->getRequest()->hasPost('outgoingSmsLimitation');
        $outgoingLimitationCount = $this->getRequest()->post('outgoingSmsLimitationCount', 1);
        $outgoingLimitationAlert = $this->getRequest()->hasPost('outgoingSmsLimitationAlert');
        $outgoingLimitationMessage = $this->getRequest()->post('outgoingSmsLimitationMessage', '');

//        var_dump($outgoingLimitation, $outgoingLimitationCount, $outgoingLimitationAlert, $outgoingLimitationMessage);die;

        $settingsModel = new \Models\Cp\Settings($this->di);
        try {
            $settingsModel->setSmsSettings($this->di['devId'], $outgoingLimitation, $outgoingLimitationCount, $outgoingLimitationAlert, $outgoingLimitationMessage);

            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The settings have been successfully updated!'));
        } catch (\Models\Cp\Settings\InvalidSmsLimitationMessageException $e) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The alert message must be between 1 and 100 characters long!'));
        }
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Device Settings');
    }

    private function deleteDevice()
    {
        $this->checkDemo($this->di['router']->getRouteUrl('settings'));

        try {
            $this->di['devicesManager']->deleteDevice($this->di['devId']);

            $this->di->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di['t']->_('The device has been successfully unassigned from your account!'));
            $this->redirect($this->di['router']->getRouteUrl('profile'));
        } catch (\Exception $e) {
            $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, "Error during operation!");
            $this->getDI()->get('logger')->addError('Error during deleting device!', array('exception' => $e));
        }
    }

    public function checkNewBackup()
    {
        $this->checkDemo($this->di['router']->getRouteUrl('settings'));

        $device = $this->di->get('currentDevice');

        if ($device['processing']) {
            $this->di->getFlashMessages()->add(FlashMessages::INFO, $this->di['t']->_('Backup data is downloading and will be available shortly! Please, wait.'));
            return;
        }

        $deviceiCloudRecord = new \CS\Models\Device\DeviceICloudRecord($this->di['db']);
        $deviceiCloudRecord->loadByDevId($this->di['devId']);

        try {
            $backup = new \CS\ICloud\Backup($deviceiCloudRecord->getAppleId(), $deviceiCloudRecord->getApplePassword());
            $devices = $backup->getAllDevices();

            $deviceBackupData = null;
            foreach ($devices as $value) {
                if ($value['SerialNumber'] == $device['unique_id']) {
                    $deviceBackupData = $value;
                }
            }
            
            $defaultBackupNotFoundMessage = "Device backup not found. Make sure backup is enabled on the device and upload it manually.";

            if ($deviceBackupData === null) {
                $deviceiCloudRecord->setLastError(\CS\Models\Device\DeviceICloudRecord::ERROR_DEVICE_NOT_FOUND_ON_ICLOUD)->save();
                $this->di['flashMessages']->add(FlashMessages::ERROR, $defaultBackupNotFoundMessage);
                return;
            }

            $lastCommited = $deviceBackupData['Committed'] > 0 ? 1 : 0;

            if ($deviceBackupData['SnapshotID'] == 1 && !$lastCommited) {
                $deviceiCloudRecord->setProcessing(\CS\Models\Device\DeviceICloudRecord::PROCESS_FIRST_COMMIT)
                        ->setLastError(\CS\Models\Device\DeviceICloudRecord::ERROR_NONE)
                        ->setLastCommited(0)
                        ->setLastSync(time())
                        ->save();

                $this->di['flashMessages']->add(FlashMessages::ERROR, $defaultBackupNotFoundMessage);
                return;
            }
            
            $isNew = strtotime($deviceBackupData['LastModified']) > $deviceiCloudRecord->getLastBackup();
            if (!$isNew && $deviceiCloudRecord->getLastError() == 0) {
                $this->di->getFlashMessages()->add(FlashMessages::INFO, $this->di['t']->_('New backups not found, try again later.'));
                return;
            }

            $deviceiCloudRecord->setProcessing(\CS\Models\Device\DeviceICloudRecord::PROCESS_IMPORT)
                    ->setLastCommited($lastCommited)
                    ->setProcessingStartTime(time())
                    ->save();

            $queueManager = new \CS\Queue\Manager($this->di['queueClient']);

            if (!$queueManager->addTaskDevice('downloadChannel-priority', $deviceiCloudRecord)) {
                throw new Exception("Error during add to queue");
            }

            if ($isNew) {
                $this->di->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di['t']->_('We found new data for this device. Backup is queued for download.'));
            } else {
                $this->di->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di['t']->_('We found new data for this device. Backup is queued for download.'));
            }
            $this->di['usersNotesProcessor']->iCloudForceBackup($deviceiCloudRecord->getDevId());
        } catch (\CS\ICloud\AuthorizationException $e) {
            $deviceiCloudRecord->setLastError(\CS\Models\Device\DeviceICloudRecord::ERROR_AUTHENTICATION)->save();
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('iCloud Authorization Error. Please %schange the password%s', array(
                        '<a href="' . $this->getDI()->getRouter()->getRouteUri('profileICloudPasswordReset') . '/'. $this->di["devId"].'">',
                        '</a>'
            )));
        } catch (\CS\ICloud\TwoStepVerificationException $e) {
            $deviceiCloudRecord->setLastError(\CS\Models\Device\DeviceICloudRecord::ERROR_TWO_STEP_VERIFICATION)->save();
            $this->di['flashMessages']->add(FlashMessages::ERROR, "This Apple ID is protected with a two-step verification. Please turn it off and try again. Follow the link to learn more: https://support.apple.com/en-us/HT202664");
        } catch (Exception $e) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('New Data Upload Error. Please contact Customer %sSupport%s', array(
                        '<a href="mailto:support@pumpic.com">',
                        '</a>'
            )));
            $this->getDI()->get('logger')->addError('Error during new backup request!', array('exception' => $e));
        }
    }

    protected function isModulePaid()
    {
        return true;
    }

}
