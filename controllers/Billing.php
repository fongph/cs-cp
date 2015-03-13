<?php

namespace Controllers;

use CS\ICloud\Backup as ICloudBackup,
    CS\Models\License\LicenseRecord,
    CS\Users\UsersNotes,
    System\FlashMessages,
    CS\Settings\GlobalSettings,
    CS\Devices\Manager as DevicesManager,
    CS\Models\License\LicenseNotFoundException;

class Billing extends BaseController
{

    public function indexAction()
    {
        $billingModel = new \Models\Billing($this->di);

        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $billingModel->getDataTableData(
                    $this->auth['id'], $dataTableRequest->buildResult(array('active'))
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        if($this->di->get('isWizardEnabled')) {
            $this->view->title = $this->di->getTranslator()->_('Subscriptions');
            
        } else $this->view->title = $this->di->getTranslator()->_('Payments & Devices');
        
        $this->view->unlimitedValue = \CS\Models\Limitation\LimitationRecord::UNLIMITED_VALUE;
        $this->view->buyUrl = GlobalSettings::getMainURL($this->di['config']['site']) . '/buy.html';
        $this->view->bundles = $billingModel->getBundlesList($this->auth['id']);

        $this->setView('billing/index.htm');
    }

    public function addICloudDeviceAction()
    {
        $devicesManager = new DevicesManager($this->di['db']);
        $licenseRecord = new LicenseRecord($this->di['db']);

        try {

            $licenseRecord->load($this->getRequest()->get('license'));
            if ($licenseRecord->getUserId() != $this->auth['id'] || $licenseRecord->getStatus() != LicenseRecord::STATUS_AVAILABLE
                    //todo $licenseRecord->getProduct()->getGroup()
                    || $licenseRecord->getOrderProduct()->getProduct()->getGroup() != 'premium')
                throw new LicenseNotFoundException;
            if ($_POST) {
                $iCloud = new ICloudBackup($_POST['email'], $_POST['password']);
                $devices = $iCloud->getDevices();

                if (empty($devices)) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Account has no devices. Please try another'));
                    $this->redirect($this->di['router']->getRouteUrl('billingAddICloudDevice'));
                } else
                    $devices = $devicesManager->iCloudMergeWithLocalInfo($this->auth['id'], $devices);

                if (isset($_POST['devHash']) && !empty($_POST['devHash'])) {

                    foreach ($devices as &$device) {
                        if ($device['SerialNumber'] === $_POST['devHash'] && !$device['added']) {

                            $devicesManager->setDeviceDbConfigGenerator(function($devId) {
                                if ($this->di['config']['environment'] == 'production') {
                                    return GlobalSettings::getDeviceDatabaseConfig($devId);
                                } else
                                    return $this->di['config']['dataDb'];
                            });

                            $devicesManager
                                    ->setUserId($this->auth['id'])
                                    ->setLicense($licenseRecord)
                                    ->setDeviceUniqueId($device['SerialNumber'])
                                    ->setAppleId($_POST['email'])
                                    ->setApplePassword($_POST['password'])
                                    ->setDeviceHash($device['backupUDID'])
                                    ->setName($device['DeviceName'])
                                    ->setModel($device['MarketingName'])
                                    ->setOsVer($device['ProductVersion'])
                                    ->setLastBackup($device['LastModified'])
                                    ->setQuotaUsed($device['QuotaUsed'])
                                    ->setAfterSave(function() use ($devicesManager) {
                                        $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_(
                                                        'New device added'
                                        ));

                                        $userNotes = new UsersNotes($this->di['db']);
                                        $userNotes->addSystemNote($this->auth['id'], UsersNotes::TYPE_SYSTEM, null, null, "New device added {$devicesManager->getProcessedDevice()->getName()} " . json_encode(array(
                                                    'dev_id' => $devicesManager->getProcessedDevice()->getUniqueId()
                                        )));
                                        $userNotes->addSystemNote($this->auth['id'], UsersNotes::TYPE_SYSTEM, null, null, "Assign {$devicesManager->getLicense()->getOrderProduct()->getProduct()->getName()} to device {$devicesManager->getProcessedDevice()->getName()} " . json_encode(array(
                                                    'device_id' => $devicesManager->getProcessedDevice()->getId(),
                                                    'license_id' => $devicesManager->getLicense()->getId()
                                                ))
                                        );

                                        $queueManager = new \CS\Queue\Manager($this->di['queueClient']);

                                        if ($queueManager->addDownloadTask($devicesManager->getICloudDevice())) {
                                            $devicesManager->getICloudDevice()->setProcessing(1);
                                        } else
                                            $devicesManager->getICloudDevice()->setLastError($queueManager->getError());

                                        $devicesManager->getICloudDevice()->save();
                                    })
                                    ->addICloudDevice();

                            $this->redirect($this->di['router']->getRouteUrl('billing'));
                            break;
                        }
                    }
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Incorrect device!'));
                }

                $this->view->title = $this->di->getTranslator()->_('Choose iCloud Device');
                $this->view->appleID = $_POST['email'];
                $this->view->applePassword = $_POST['password'];
                $this->view->devices = $devices;
                $this->setView('billing/addICloudDevice.htm');
                return;
            }
        } catch (LicenseNotFoundException $e) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid license!'));
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        } catch (\CS\ICloud\AuthorizationException $e) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Wrong Apple ID or password'));
            $this->redirect($this->di['router']->getRouteUrl('billingAddICloudDevice') . '?license=' . $this->getRequest()->get('license'));
        } catch (\Exception $e) {
            $this->di['logger']->addCritical(get_class($e) . " {$e->getMessage()} {$e->getFile()}[{$e->getLine()}] " . p($e));
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Unexpected Error. Please try later or contact us!'));
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        }

        $this->view->title = $this->di->getTranslator()->_('Assign iCloud Device');
        $this->setView('billing/iCloudAccount.htm');
    }

    public function assignDeviceAction()
    {
        $devicesManager = new DevicesManager($this->di['db']);
        $licenseRecord = new LicenseRecord($this->di['db']);
        $license = $this->getRequest()->get('license');
        $this->view->iCloudLicenseAvailable = false;

        try {
            $licenseRecord->load($license);
            if ($licenseRecord->getUserId() != $this->auth['id'] || $licenseRecord->getStatus() != LicenseRecord::STATUS_AVAILABLE) {
                throw new LicenseNotFoundException;
            }
        } catch (LicenseNotFoundException $e) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid license!'));
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        }

        //todo $licenseRecord->getProduct()->getGroup()
        if (($this->auth['id'] == 78 || $this->auth['id'] == 9) && $licenseRecord->getOrderProduct()->getProduct()->getGroup() == 'premium')
            $this->view->iCloudLicenseAvailable = true;

        $list = $devicesManager->getDevicesToAssign($this->auth['id']);

        if (!count($list) && !$this->view->iCloudLicenseAvailable) {
            $this->redirect($this->di['router']->getRouteUrl('billingAddDevice') . '?license=' . $license);
        }

        $device = $this->getRequest()->get('device');
        if ($device !== null) {
            if (!isset($list[$device])) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid device!'));
                $this->redirect($this->di['router']->getRouteUrl('billing'));
            }

            if (!$this->getRequest()->hasGet('confirm') && $devicesManager->hasDevicePackageLicense($device)) {
                $this->view->deviceConfirm = $device;
            } else {
                $devicesManager->removeDeviceLicenses($device);
                
                $devicesManager->assignLicenseToDevice($license, $device);
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Device successfully assigned to your license!'));

                $licInfo = (new \Models\Billing($this->di))
                        ->getLicenseDeviceInfo($license);

                if ($licInfo !== false) {
                    (new \Models\Users($this->di))
                            ->addSystemNote($this->auth['id'], "Assign {$licInfo['product_name']} to device {$licInfo['device_name']} " . json_encode(array(
                                        'device_id' => $licInfo['dev_id'],
                                        'license_id' => $licInfo['license_id']
                                    ))
                    );
                }

                $this->redirect($this->di['router']->getRouteUrl('billing'));
            }
        }

        $this->view->title = $this->di->getTranslator()->_('Assign Device');
        $this->view->devices = $list;
        $this->view->license = $license;
        $this->setView('billing/assignDevice.htm');
    }

    public function addDeviceAction()
    {
        $this->view->title = $this->di->getTranslator()->_('New Device');

        $license = $this->getRequest()->get('license');

        $devicesManager = new DevicesManager($this->di['db']);

        if ($this->getRequest()->hasGet('code')) {
            $this->completeAddDevice($devicesManager);
        }

        if ($license != null &&
                !$devicesManager->isUserLicenseAvailable($license, $this->auth['id'])) {

            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid license!'));
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        }

        try {
            $code = $devicesManager->getUserDeviceAddCode($this->auth['id'], $license);
        } catch (CS\Devices\Manager\DeviceCodeGenerationException $e) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, "Error during add device! Pleace try again later!");
            $this->di['logger']->addCritical("Device code generation failed!");
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        }

        $this->view->code = $code;
        $this->view->displayCode = str_pad($code, 4, '0', STR_PAD_LEFT);

        $this->setView('billing/addDevice.htm');
    }

    private function completeAddDevice(DevicesManager $devicesManager)
    {
        $code = $this->getRequest()->get('code');
        $info = $devicesManager->getUserAddCodeInfo($this->auth['id'], $code);

        if ($info === false) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, "Code not found!");
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        }

        if ($info['assigned']) {
            $this->di->getFlashMessages()->add(FlashMessages::SUCCESS, "Your device successfully added!");
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        }

        if ($info['expired']) {
            $this->di->getFlashMessages()->add(FlashMessages::INFO, "Code was expired. We've generated new code for you. Please enter it on mobile phone.");
        } else {
            $this->di->getFlashMessages()->add(FlashMessages::INFO, "It looks you haven't entered code on mobile yet. Please do it now.");
        }

        if ($info['license_id'] !== null) {
            $this->redirect($this->di['router']->getRouteUrl('billingAddDevice') . '?license=' . $info['license_id']);
        } else {
            $this->redirect($this->di['router']->getRouteUrl('billingAddDevice'));
        }
    }

    public function licenseAction()
    {
        $this->view->title = $this->di->getTranslator()->_('Plan view');

        $billingModel = new \Models\Billing($this->di);
        $license = $billingModel->getUserLicenseInfo($this->auth['id'], $this->params['id']);

        if ($license == false) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, "Plan was not found!");
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        }

        $this->view->license = $license;
        $this->setView('billing/license.htm');
    }

    public function disableLicenseAction()
    {
        $billingModel = new \Models\Billing($this->di);
        $license = $billingModel->getUserLicenseInfo($this->auth['id'], $this->params['id'], false);

        if ($license == false) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, "Plan was not found!");
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        }

        $billingModel->disableSubscription($license['payment_method'], $license['reference_number']);
        $this->redirect($this->di->getRouter()->getRouteUrl('billingLicense', array('id' => $license['id'])));
    }

    public function enableLicenseAction()
    {
        $billingModel = new \Models\Billing($this->di);
        $license = $billingModel->getUserLicenseInfo($this->auth['id'], $this->params['id'], false);

        if ($license == false) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, "Plan was not found!");
            $this->redirect($this->di->getRouter()->getRouteUrl('billing'));
        }

        $billingModel->enableSubscription($license['payment_method'], $license['reference_number']);
        $this->redirect($this->di->getRouter()->getRouteUrl('billingLicense', array('id' => $license['id'])));
    }

}
