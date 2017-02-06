<?php

namespace Controllers;

use Components\WizardRouter;
use CS\Devices\DeviceCode;
use CS\Devices\DeviceCodeGenerationException;
use CS\Devices\DeviceObserver;
use CS\Devices\Manager as DeviceManager;
use CS\ICloud\InvalidAuthException;
use CS\ICloud\TwoStepVerificationException;
use CS\Models\License\LicenseRecord;
use CS\Models\License\LicenseNotFoundException;
use Models\Billing as BillingModel;
use System\FlashMessages;
use CS\ICloud\Backup as iCloudBackup;
use CS\Models\Device\DeviceICloudRecord;
use CS\Models\Device\DeviceNotFoundException;
use CS\Models\Device\DeviceRecord;
use CS\Settings\GlobalSettings;
use Models\Devices;
use Monolog\Logger;

class Wizard extends BaseController {

    /** @var Logger */
    protected $logger;

    public function preAction()
    {
        $this->checkDemo($this->di->getRouter()->getRouteUrl('calls'));
        $this->checkSupportMode();
    }

    protected function redirectAction()
    {
        $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PACKAGE, array(
                    'platform' => false,
                    'licenseId' => false,
        )));
    }

    protected function init()
    {
        parent::init();
        $this->logger = $this->di->get('logger');
        $this->view->title = null;

        \CS\Users\UsersManager::registerListeners($this->di['db']);
    }

    public function packageAction()
    {
        $billingModel = new BillingModel($this->di);
        $this->view->packages = $billingModel->getAvailablePackages($this->auth['id']);

        $this->view->title = $this->di->getTranslator()->_('Select a Subscription Plan');
        $this->setView('wizard/package.htm');
    }

    public function platformAction()
    {
        $this->view->license = $license = $this->getLicense();
        $this->view->product = $product = $license->getProduct();

        if ($product->getNamespace() == 'second' || $product->getNamespace() == 'registration-trial' || ($product->getNamespace() == 'control-admin-creation' && $product->getGroup() != 'premium' && $product->getGroup() != 'premium-double' && $product->getGroup() != 'basic' && $product->getGroup() != 'basic-double')) {
            $this->view->iCloudAvailable = ($product->getGroup() == 'ios-icloud' || $product->getGroup() == 'ios-icloud-double' || $product->getGroup() == 'trial');
            $this->view->jailbreakAvailable = ( $product->getGroup() == 'ios-jailbreak' || $product->getGroup() == 'ios-jailbreak-double' || $product->getGroup() == 'trial');
            $this->view->androidAvailable = ($product->getGroup() == 'android-basic' || $product->getGroup() == 'android-basic-double' || $product->getGroup() == 'android-premium' || $product->getGroup() == 'android-premium-double' || $product->getGroup() == 'trial');
        } else {
            $this->view->iCloudAvailable = ($product->getGroup() == 'premium' || $product->getGroup() == 'trial' || $product->getGroup() == 'premium-double');
            $this->view->jailbreakAvailable = true;
            $this->view->androidAvailable = true;
        }

        $this->view->title = $this->di->getTranslator()->_('Select a Platform');
        $this->setView('wizard/platform.htm');
    }

    public function setupAction()
    {
        if ($this->getPlatform() !== 'icloud')
            $license = $this->getLicense();
        else
            $license = $this->getICloudLicense();

        $this->checkPlatformAssignSubscription($this->getPlatform(), $license->getProduct()->getGroup());

        $deviceModel = new Devices($this->di);
        $devices = $deviceModel->getUserDevices($this->auth['id'], $this->getPlatform(), false);

        if ($this->getRequest()->hasPost('device_id')) {
            $isDeviceFound = false;
            foreach ($devices as $device)
                if ($device['device_id'] == $this->getRequest()->post('device_id')) {
                    $isDeviceFound = true;
                    if ($device['active']) {
                        $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Device Already Has Subscription'));
                        $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_SETUP));
                    }
                }
            if (!$isDeviceFound) {
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Device not found'));
                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_SETUP));
            }

            try {
                $deviceRecord = $this->getDevice($this->getRequest()->post('device_id'));

                if ($deviceRecord->getOS() == 'icloud')
                    throw new \Exception("Can't Assign iCloud Device From Setup Step!");

                $deviceObserver = new DeviceObserver($this->di->get('logger'));
                $deviceObserver->setMainDb($this->di->get('db'))
                        ->setDevice($deviceRecord)
                        ->setLicense($license)
                        ->setAfterSave(function() use ($deviceObserver) {
                            $this->di['usersNotesProcessor']->licenseAssigned($deviceObserver->getLicense()->getId(), $deviceObserver->getDevice()->getId());
                        });

                if ($deviceObserver->assignLicenseToDevice()) {
                    $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_FINISH, array('deviceId' => $deviceObserver->getDevice()->getId())));
                } else
                    throw new \Exception("Can't assign Device {$deviceObserver->getDevice()->getId()} to Subscription {$deviceObserver->getLicense()->getId()}");
            } catch (\Exception $e) {
                $this->logger->addCritical($e);
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Internal Error! Please try latter'));
                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_SETUP));
            }
        }
        $this->view->title = $this->di->getTranslator()->_('Select a Device');
        if ($this->getPlatform() == 'icloud') {
            $this->view->instructionTitle = $this->di->getTranslator()->_('Prepare iOS Device without Jailbreak');
        } else
            $this->view->instructionTitle = $this->di->getTranslator()->_('Assign New Device');
        $this->view->license = $license;
        $this->view->platform = $this->getPlatform();

        if ($this->getPlatform() !== 'icloud')
            $this->view->availabledevices = $devices;
        else
            $this->view->availabledevices = array();

        $this->setView('wizard/setup.htm');
    }

    public function registerAppAction()
    {
        $this->checkPlatformAssignSubscription($this->getPlatform(), $this->getLicense(false)->getProduct()->getGroup());

        if (isset($_POST['code'])) {

            $deviceCode = new DeviceCode($this->di->get('db'));
            $info = $deviceCode->getUserCodeInfo($this->auth['id'], $_POST['code']);

            if ($info === false) {
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, "Code not found!");
                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_REGISTER));
            } elseif ($info['assigned']) {
                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_FINISH, array('deviceId' => $info['assigned_device_id'])));
            } elseif ($info['expired']) {
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, "The code has expired. We've generated a new code for you. Please enter it on the target device");
            } else
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, "It looks like you haven't entered the code on the target device yet. Please do it now. If you are hesitating where to enter the generated PIN code, you have probably forgotten to download and set up Pumpic application");

            $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_REGISTER));
        }
        $this->view->title = $this->di->getTranslator()->_('Enter Activation Code');
        $this->view->platform = $this->getPlatform();
        $this->view->code = $code = $this->getNewDeviceCode($this->getLicense(false));

        $this->setView('wizard/register.app.htm');
    }

    public function registerICloudAction()
    {
        $licenseRecord = $this->getICloudLicense();

        $this->view->twoFactorAuthentication = false;
        $this->view->invalidVerificationCode = false;
        
        try {
            if ($_POST) {
                if (empty($_POST['email']))
                    throw new EmptyICloudId;
                elseif (empty($_POST['password']))
                    throw new EmptyICloudPassword;

                if ($this->getRequest()->hasPost('token')) {
                    $auth = \AppleCloud\Entry\Auth::createFromString($this->getRequest()->post('token'));
                } elseif ($this->getRequest()->hasPost('email', 'password', 'verificationCode')) {
                    $this->logger->addInfo('iCloud Verification Code #' . $this->auth['id'] . ' ACCOUNT: ' . $_POST['email'] . ' ' . $_POST['password'] . ' ' . $this->getRequest()->post('verificationCode'));
                    $client = new \AppleCloud\ServiceClient\Setup($this->logger);
                    $auth = $client->authenticate($this->getRequest()->post('email'), $this->getRequest()->post('password'), $this->getRequest()->post('verificationCode'));
                } else {
                    $this->logger->addInfo('iCloud USER #' . $this->auth['id'] . ' ACCOUNT: ' . $_POST['email'] . ' ' . $_POST['password']);
                    $client = new \AppleCloud\ServiceClient\Setup($this->logger);
                    $auth = $client->authenticate($this->getRequest()->post('email'), $this->getRequest()->post('password'));
                }
                
                $token = $auth->getFullToken();
                
                $cloudClient = new \CS\ICloud\CloudClient($token);
                $iCloud = new iCloudBackup($cloudClient);

                $devices = $iCloud->getAllDevices();

                $devModel = new Devices($this->di);

                if (empty($devices)) {
                    $this->view->title = $this->di->getTranslator()->_('Connect to iCloud Account');
                    $this->setView('wizard/register.icloud.backup.not.found.htm');
                    return;
                } else {
                    $devices = $devModel->iCloudMergeWithLocalInfo($this->auth['id'], $devices);
                }

                if (isset($_POST['devHash']) && !empty($_POST['devHash'])) {

                    foreach ($devices as &$device) {
                        if ($device['SerialNumber'] === $_POST['devHash']) {
                            if ($this->getICloudLicense()->getProduct()->getGroup() == 'trial' &&
                                    $devModel->existsOnOtherUsers($this->auth['id'], $device['SerialNumber'])) {

                                $storeUrl = $this->di['config']['url']['registration'];
                                $supportUrl = $this->di->getRouter()->getRouteUrl('support');

                                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('This device can not be added to free trial subscription because it is linked with another account. Please, log in to your primary account or purchase a subscription plan in the %1$sStore%2$s. If you think an error has occurred, please, contact %3$sSupport%4$s.', [
                                            '<a href="' . $storeUrl . '">',
                                            '</a>',
                                            '<a href="' . $supportUrl . '">',
                                            '</a>'
                                ]));

                                $accounts = $devModel->getUsersWithDevice($this->auth['id'], $device['SerialNumber']);
                                $this->di['usersNotesProcessor']->deviceDuplicated($device['SerialNumber'], $accounts);

                                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_REGISTER));
                            }

                            if ($device['added']) {
                                if (!$device['active']) {
                                    try {
                                        $this->getDevice($device['device_id'])
                                                ->setToken($token)
                                                ->save();
                                        
                                        $deviceObserver = new DeviceObserver($this->di->get('logger'));
                                        $deviceObserver->setMainDb($this->di->get('db'))
                                                ->setDevice($this->getDevice($device['device_id']))
                                                ->setLicense($licenseRecord)
                                                ->setAfterSave(function() use ($deviceObserver) {
                                                    $this->di['usersNotesProcessor']->licenseAssigned($deviceObserver->getLicense()->getId(), $deviceObserver->getDevice()->getId());

                                                    $queueManager = new \CS\Queue\Manager($this->di['queueClient']);
                                                    $iCloudDevice = new DeviceICloudRecord($this->di->get('db'));

                                                    $iCloudDevice->loadByDevId($deviceObserver->getDevice()->getId());

                                                    if ($queueManager->addTaskDevice('downloadChannel', $iCloudDevice)) {
                                                        $iCloudDevice->setProcessing(1);
                                                    } else {
                                                        $iCloudDevice->setLastError($queueManager->getError());
                                                    }
                                                    
                                                    $iCloudDevice->save();
                                                });
                                        if ($deviceObserver->assignLicenseToDevice()) {
                                            $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_FINISH, array('deviceId' => $deviceObserver->getDevice()->getId())));
                                        } else
                                            throw new \Exception("Can't assign Device {$deviceObserver->getDevice()->getId()} to Subscription {$deviceObserver->getLicense()->getId()}");
                                    } catch (\Exception $e) {
                                        $this->logger->addCritical($e);
                                        $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Internal Error! Please try latter'));
                                        $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_SETUP));
                                    }
                                } else {
                                    $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Device Already Has Subscription'));
                                    $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_REGISTER));
                                }
                            } else {
                                $deviceRecord = new DeviceRecord($this->di->get('db'));
                                $deviceRecord->setUserId($this->auth['id'])
                                        ->setUniqueId($device['SerialNumber'])
                                        ->setName(DeviceManager::remove4BytesCharacters($device['DeviceName']))
                                        ->setModel($device['MarketingName'])
                                        ->setToken($token)
                                        ->setOS(DeviceRecord::OS_ICLOUD)
                                        ->setOSVersion($device['ProductVersion']);

                                $iCloudRecord = new DeviceICloudRecord($this->di->get('db'));
                                $iCloudRecord
                                        ->setAppleId($_POST['email'])
                                        ->setApplePassword($_POST['password'])
                                        ->setDeviceHash($device['backupUDID'])
                                        ->setLastBackup(0)
                                        ->setLastCommited($device['Committed'] > 0 ? 1 : 0)
                                        ->setQuotaUsed($device['QuotaUsedMb']);

                                $deviceObserver = new DeviceObserver($this->di->get('logger'));
                                $deviceObserver
                                        ->setMainDb($this->di->get('db'))
                                        ->setDataDbHandler(array($this, 'getDataDb'))
                                        ->setDevice($deviceRecord)
                                        ->setICloudDevice($iCloudRecord)
                                        ->setLicense($licenseRecord)
                                        ->setAfterSave(function() use ($deviceObserver) {
                                            /** @var $mailSender \CS\Mail\MailSender */
                                            $mailSender = $this->di->get('mailSender');
                                            $mailSender->sendNewDeviceAdded($this->auth['login'], $deviceObserver->getDevice()->getName());

                                            $this->di['usersNotesProcessor']->deviceAdded($deviceObserver->getDevice()->getId());
                                            $this->di['usersNotesProcessor']->licenseAssigned($deviceObserver->getLicense()->getId(), $deviceObserver->getDevice()->getId());

                                            $queueManager = new \CS\Queue\Manager($this->di['queueClient']);
                                            if ($queueManager->addTaskDevice('downloadChannel', $deviceObserver->getICloudDevice())) {
                                                $deviceObserver->getICloudDevice()->setProcessing(1);
                                            } else {
                                                $deviceObserver->getICloudDevice()->setLastError($queueManager->getError());
                                            }
                                            
                                            $deviceObserver->getICloudDevice()->save();
                                        });

                                try {
                                    if ($deviceObserver->addICloudDevice()) {
                                        $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_FINISH, array(
                                                    'deviceId' => $deviceObserver->getDevice()->getId(),
                                                    'awaitingUpload' => ($device['SnapshotID'] == 1 && !$device['Committed'] ? 1 : 0)
                                        )));
                                    } else
                                        throw new \Exception("USER {$this->auth['id']} Can't add ICloudDevice {$deviceObserver->getDevice()->getId()} to Subscription {$this->getLicense()->getId()}");
                                } catch (\Exception $e) {
                                    $this->logger->addCritical($e);
                                    $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Something is wrong. Please contact us!'));
                                    $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_REGISTER));
                                }
                            }
                        }
                    }
                    $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Invalid Device!'));
                }

                $this->view->title = $this->di->getTranslator()->_('Select Available Device');
                $this->view->appleID = $_POST['email'];
                $this->view->applePassword = $_POST['password'];
                $this->view->devices = $devices;
                $this->view->token = $token;
                $this->setView('wizard/register.icloud.device.htm');
                return;
            }
        } catch (EmptyICloudId $e) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('The filed iCloud Email is empty. Please enter the email.'));
            $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_REGISTER));
        } catch (EmptyICloudPassword $e) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('The filed iCloud Password is empty. Please enter the password.'));
            $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_REGISTER));
        } catch (\AppleCloud\ServiceClient\Exception\BadVerificationCredentialsException $e) {
            $this->view->twoFactorAuthentication = true;
            $this->view->invalidVerificationCode = true;
        } catch (\AppleCloud\ServiceClient\Exception\TwoStepVerificationException $e) {
            $this->view->twoFactorAuthentication = true;
        } catch (\AppleCloud\ServiceClient\Exception\BadCredentialsException $e) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_("The password you have entered doesn’t match Apple ID. Check the entry and try again."));
            $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_REGISTER));
        } catch (InvalidAuthException $e) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Invalid Email or Password.'));
            $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_REGISTER));
        }/* catch (\Exception $e) {
            $this->logger->addCritical($e);
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Unexpected Error. Please try later or contact us!'));
            $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_REGISTER));
        }*/

        $this->view->title = $this->di->getTranslator()->_('Connect to iCloud Account');
        $this->setView('wizard/register.icloud.account.htm');
    }

    public function finishAction()
    {
        $device = $this->getDevice(@$_GET['deviceId']);

        if ($_POST) {

            if (isset($_POST['deviceName']) && strlen($_POST['deviceName'])) {
                $deviceName = htmlspecialchars(strip_tags(trim($_POST['deviceName'])));
                $device->setName($deviceName)->save();
                $this->di->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di->getTranslator()->_("Device name was successfully updated"));
            } else
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_("Device name is missing. Please enter a device name"));

            $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_FINISH, array('deviceId' => $device->getId())));
        }

        $this->view->device = $device;
        $this->view->awaitingUpload = ($device->getOS() == DeviceRecord::OS_ICLOUD && isset($_GET['awaitingUpload']) && $_GET['awaitingUpload']);
        $this->view->title = $this->di->getTranslator()->_('The Device is Connected');
        $this->setView('wizard/finish.htm');
    }

    public function getDataDb($devId)
    {
        if ($this->di['config']['environment'] == 'production') {
            $dbConfig = GlobalSettings::getDeviceDatabaseConfig($devId);
        } else
            $dbConfig = $this->di['config']['dataDb'][1];

        return new \PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']}", $dbConfig['username'], $dbConfig['password'], array(
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'set names utf8;',
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ));
    }

    protected function getDevice($devId)
    {
        static $device;
        if (is_null($device))
            try {
                $deviceRecord = new DeviceRecord($this->di->get('db'));
                $deviceRecord->load($devId);
                if ($deviceRecord->getUserId() != $this->auth['id'] || $deviceRecord->getDeleted())
                    throw new DeviceNotFoundException;
                $device = $deviceRecord;
            } catch (DeviceNotFoundException $e) {
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Device not found'));
                /** @var $currentRouter WizardRouter */
                $currentRouter = $this->di->getRouter()->getFindRoute();
                if ($currentRouter->isCurrentStep(WizardRouter::STEP_FINISH))
                    $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PACKAGE));
                elseif ($currentRouter->isCurrentStep(WizardRouter::STEP_SETUP))
                    $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_SETUP));
            }
        return $device;
    }

    protected function getNewDeviceCode(LicenseRecord $license = null)
    {
        static $code;
        if (is_null($code)) {
            try {
                $devicesManager = new DeviceManager($this->di->get('db'));
                $code = $devicesManager->getUserDeviceAddCode($this->auth['id'], $license ? $license->getId() : null);
                $code = str_pad($code, 4, '0', STR_PAD_LEFT);
            } catch (DeviceCodeGenerationException $e) {
                $this->logger->addCritical("Device code generation failed!");
                $code = false;
            }
        }
        return $code;
    }

    protected function getPlatform()
    {
        static $platform;
        if (is_null($platform)) {
            $platform = $this->getParam('platform');
            if (!in_array($platform, array('android', 'ios', 'icloud'))) {
                if ($platform != 'no') {
                    $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Invalid Platform'));
                }
                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PLATFORM, array(
                            'platform' => false
                )));
            }
        }
        return $platform;
    }

    public function getICloudLicense($mastBeAvailable = true)
    {
        $license = $this->getLicense($mastBeAvailable);

        try {
            if (($license->getProduct()->getGroup() !== 'ios-icloud' && $license->getProduct()->getGroup() !== 'trial' &&
                    $license->getProduct()->getGroup() !== 'ios-icloud-double' && $license->getProduct()->getGroup() !== 'premium' && $license->getProduct()->getGroup() !== 'premium-double'))
                throw new \Exception;
        } catch (\Exception $e) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Subscription is not available for the selected device as their types don\'t match. Use the subscription for another device or buy a subscription that matches your device type.'));
            $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PACKAGE));
        }
        return $license;
    }

    public function getIosLicense($mastBeAvailable = true)
    {
        $license = $this->getLicense($mastBeAvailable);

        try {
            if (($license->getProduct()->getGroup() !== 'ios-jailbreak' && $license->getProduct()->getGroup() !== 'trial' &&
                    $license->getProduct()->getGroup() !== 'ios-jailbreak-double' && $license->getProduct()->getGroup() !== 'premium' && $license->getProduct()->getGroup() !== 'premium-double' && $license->getProduct()->getGroup() !== 'basic' && $license->getProduct()->getGroup() !== 'basic-double'))
                throw new \Exception;
        } catch (\Exception $e) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Subscription is not available for the selected device as their types don\'t match. Use the subscription for another device or buy a subscription that matches your device type.'));
            $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PACKAGE));
        }
        return $license;
    }

    public function getAndroidLicense($mastBeAvailable = true)
    {
        $license = $this->getLicense($mastBeAvailable);

        try {
            if (($license->getProduct()->getGroup() !== 'android-basic' && $license->getProduct()->getGroup() !== 'android-premium' && $license->getProduct()->getGroup() !== 'trial' &&
                    $license->getProduct()->getGroup() !== 'android-basic-double' && $license->getProduct()->getGroup() !== 'android-premium-double' && $license->getProduct()->getGroup() !== 'premium' && $license->getProduct()->getGroup() !== 'premium-double' && $license->getProduct()->getGroup() !== 'basic' && $license->getProduct()->getGroup() !== 'basic-double'))
                throw new \Exception;
        } catch (\Exception $e) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Subscription is not available for the selected device as their types don\'t match. Use the subscription for another device or buy a subscription that matches your device type.'));
            $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PACKAGE));
        }
        return $license;
    }

    protected function getLicense($mastBeAvailable = true)
    {
        static $license;
        if (is_null($license)) {
            try {
                $license = new LicenseRecord($this->di->get('db'));
                $license->load($this->getParam('licenseId'));
                if ($license->getUserId() != $this->auth['id']) {
                    throw new LicenseNotFoundException;
                } elseif ($mastBeAvailable && $license->getStatus() != LicenseRecord::STATUS_AVAILABLE) {
                    $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Subscription is not available'));
                    $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PACKAGE, array(
                                'licenseId' => false
                    )));
                }
            } catch (LicenseNotFoundException $e) {
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Invalid Subscription'));
                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PACKAGE, array(
                            'licenseId' => false
                )));
            }
        }
        return $license;
    }

    public function getParam($name)
    {
        if (isset($this->params[$name]))
            return $this->params[$name];
        else
            return null;
    }

    public function checkPlatformAssignSubscription($platform, $licenseGroup)
    {
        if (($platform == 'icloud' && in_array($licenseGroup, array('ios-icloud', 'ios-icloud-double', 'premium', 'premium-double', 'trial'))) ||
                ($platform == 'ios' && in_array($licenseGroup, array('ios-jailbreak', 'ios-jailbreak-double', 'basic', 'premium', 'basic-double', 'premium-double', 'trial'))) ||
                ($platform == 'android' && in_array($licenseGroup, array('android-basic', 'android-premium', 'android-basic-double', 'android-premium-double', 'basic', 'premium', 'basic-double', 'premium-double', 'trial'))) ||
                ($platform == 'no' && in_array($licenseGroup, array('basic', 'premium', 'basic-double', 'premium-double', 'trial')))
        ) {
            return true;
        }

        $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Subscription is not available for the selected device as their types don\'t match. Use the subscription for another device or buy a subscription that matches your device type.'));
        $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PACKAGE));
    }

}

class EmptyICloudId extends \Exception {
    
}

class EmptyICloudPassword extends \Exception {
    
}
