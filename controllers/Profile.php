<?php

namespace Controllers;

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

    public function preAction()
    {
        $this->checkDemo($this->di['router']->getRouteUrl('cp'));
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
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
                        $this->auth['id'], $this->getRequest()->post('oldPassword'), $this->getRequest()->post('newPassword'), $this->getRequest()->post('newPassword2')
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

    public function postAction()
    {
        parent::postAction();

        $this->view->title = $this->di->getTranslator()->_('Your Profile');
    }

    public function changeICloudPasswordAction()
    {
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
                    $iCloud->authenticate(); //or throw exception

                    $iCloudRecord->setApplePassword($this->getRequest()->post('newPassword'));
                    if($iCloudRecord->getLastError() == BackupQueueUnit::ERROR_AUTHENTICATION)
                        $iCloudRecord->setLastError(BackupQueueUnit::ERROR_NONE);
                    $iCloudRecord->save();

                    $this->di->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di->getTranslator()->_('You have successfully updated iCloud password. New iCloud backup will be loaded shortly.'));
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
            
        } catch (AuthorizationException $e) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_("Oops, iCloud password didn't work. Please try again."));
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
