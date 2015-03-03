<?php namespace Controllers;

use Components\WizardRouter;
use CS\Devices\DeviceCode;
use CS\Devices\DeviceCodeGenerationException;
use CS\Devices\DeviceObserver;
use CS\Devices\Manager as DeviceManager,
    CS\Models\License\LicenseRecord,
    CS\Models\License\LicenseNotFoundException,
    Models\Billing as BillingModel,
    System\FlashMessages;
use CS\ICloud\AuthorizationException;
use CS\ICloud\Backup as iCloudBackup;
use CS\Models\Device\DeviceICloudRecord;
use CS\Models\Device\DeviceNotFoundException;
use CS\Models\Device\DeviceRecord;
use CS\Settings\GlobalSettings;
use CS\Users\UsersNotes;
use Models\Devices;
use Monolog\Logger;

class Wizard extends BaseController {
    
    /** @var Logger */
    protected $logger;
    
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
    }
    
    public function packageAction()
    {
        $billingModel = new BillingModel($this->di);
        $this->view->packages = $billingModel->getAvailablePackages($this->auth['id']);
        $this->setView('wizard/package.htm');
    }
    
    public function platformAction()
    {
        $this->view->license = $license = $this->getLicense();
        $this->view->product = $product = $license->getOrderProduct()->getProduct();
        $this->view->iCloudAvailable = $product->getGroup() == 'premium';
        $this->setView('wizard/platform.htm');
    }

    public function setupAction()
    {
        $deviceManager = new DeviceManager($this->di->get('db'));
        $devices = $deviceManager->getUserActiveDevices($this->auth['id'], $this->getPlatform());
        $license = $this->getLicense();
        
        if(isset($_POST['device_id'])){
            if(isset($devices[$_POST['device_id']]) && $devices[$_POST['device_id']]['active']){
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Device Already Has License'));
                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_SETUP));
            }

            try {
                $deviceObserver = new DeviceObserver($this->di->get('logger'));
                $deviceObserver->setMainDb($this->di->get('db'))
                    ->setDevice($this->getDevice($_POST['device_id']))
                    ->setLicense($license)
                    ->setAfterSave(function() use ($deviceObserver) {
                        $this->di->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di->getTranslator()->_('Device successfully assigned to your license!'));
                        $userNotes = new UsersNotes($this->di->get('db'));
                        $userNotes->addSystemNote($this->auth['id'], UsersNotes::TYPE_SYSTEM, null, null, "Assign {$deviceObserver->getLicense()->getOrderProduct()->getProduct()->getName()} to device {$deviceObserver->getDevice()->getName()} " . json_encode(array(
                                'device_id' => $deviceObserver->getDevice()->getId(),
                                'license_id' => $deviceObserver->getLicense()->getId()
                            )));
                    });
                
                if($deviceObserver->assignLicenseToDevice()) {
                    $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_FINISH, array('deviceId'=>$deviceObserver->getDevice()->getId())));
                    
                } else throw new \Exception("Can't assign Device {$deviceObserver->getDevice()->getId()} to License {$deviceObserver->getLicense()->getId()}");
                    
            } catch (\Exception $e) {
                $this->logger->addCritical($e);
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Internal Error! Please try latter'));
                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_SETUP));
            }
        }
        $this->view->devices = $devices;
        $this->view->license = $license;
        $this->view->platform = $this->getPlatform();
        $this->setView('wizard/setup.htm');
    }

    public function registerAppAction()
    {
        $this->view->license = $license = $this->getLicense();
        
        if(isset($_POST['code'])){

            $deviceCode = new DeviceCode($this->di->get('db'));
            $info =  $deviceCode->getUserCodeInfo($this->auth['id'], $_POST['code']);

            if ($info === false) {
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, "Code not found!");
                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_REGISTER));
                
            } elseif ($info['assigned']) {
                $this->di->getFlashMessages()->add(FlashMessages::SUCCESS, "Your device successfully added!");
                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_FINISH, array('deviceId'=>$info['assigned_device_id'])));
                
            } elseif ($info['expired']) {
                $this->di->getFlashMessages()->add(FlashMessages::INFO, "Code was expired. We've generated new code for you. Please enter it on mobile phone.");
                
            } else $this->di->getFlashMessages()->add(FlashMessages::INFO, "It looks you haven't entered code on mobile yet. Please do it now.");
            
            $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_REGISTER));
        }
        
        $this->view->code = $code = $this->getNewDeviceCode($license);
        
        $this->setView('wizard/register.app.htm');
    }
    
    public function registerICloudAction()
    {
        $licenseRecord = $this->getLicense();
        if ($licenseRecord->getOrderProduct()->getProduct()->getGroup() != 'premium'){
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('iCloud is Available for Premium Packages Only'));
            $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PACKAGE));
        }

        try {
            if ($_POST) {
                $iCloud = new iCloudBackup($_POST['email'], $_POST['password']);
                $devices = $iCloud->getDevices();

                $devModel = new Devices($this->di);
                
                if (empty($devices)) {
                    $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Account has no devices. Please try another'));
                    $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_REGISTER));
                    
                } else $devices = $devModel->iCloudMergeWithLocalInfo($this->auth['id'], $devices);

                if (isset($_POST['devHash']) && !empty($_POST['devHash'])) {

                    foreach ($devices as &$device) {
                        if ($device['SerialNumber'] === $_POST['devHash'] && !$device['added']) {

                            $deviceRecord = new DeviceRecord($this->di->get('db'));
                            $deviceRecord->setUserId($this->auth['id'])
                                ->setUniqueId($device['SerialNumber'])
                                ->setName($device['DeviceName'])
                                ->setModel($device['MarketingName'])
                                ->setOS(DeviceRecord::OS_ICLOUD)
                                ->setOSVersion($device['ProductVersion']);
                            
                            $iCloudRecord = new DeviceICloudRecord($this->di->get('db'));
                            $iCloudRecord
                                ->setAppleId($_POST['email'])
                                ->setApplePassword($_POST['password'])
                                ->setDeviceHash($device['backupUDID'])
                                ->setLastBackup($device['LastModified'])
                                ->setQuotaUsed($device['QuotaUsedMb']);
                            
                            $deviceObserver = new DeviceObserver($this->di->get('logger'));
                            $deviceObserver
                                ->setMainDb($this->di->get('db'))
                                ->setDataDbHandler(array($this, 'getDataDb'))
                                ->setDevice($deviceRecord)
                                ->setICloudDevice($iCloudRecord)
                                ->setLicense($licenseRecord)
                                ->setAfterSave(function() use ($deviceObserver) {
                                    $this->di->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di->getTranslator()->_('New Device Added'));
                                    /** @var $mailSender \CS\Mail\MailSender */
                                    $mailSender = $this->di->get('mailSender');
                                    $mailSender->sendNewDeviceAdded($this->auth['login'], $deviceObserver->getDevice()->getName());
                                    
                                    $userNotes = new UsersNotes($this->di['db']);
                                    $userNotes->addSystemNote($this->auth['id'], UsersNotes::TYPE_SYSTEM, null, null, "New device added {$deviceObserver->getDevice()->getName()} " . json_encode(array(
                                            'dev_id' => $deviceObserver->getDevice()->getUniqueId()
                                        )));
                                    $userNotes->addSystemNote($this->auth['id'], UsersNotes::TYPE_SYSTEM, null, null, "Assign {$deviceObserver->getLicense()->getOrderProduct()->getProduct()->getName()} to device {$deviceObserver->getDevice()->getName()} " . json_encode(array(
                                            'device_id' => $deviceObserver->getDevice()->getId(),
                                            'license_id' => $deviceObserver->getLicense()->getId()
                                        ))
                                    );

                                    $queueManager = new \CS\Queue\Manager($this->di['queueClient']);
                                    if ($queueManager->addDownloadTask($deviceObserver->getICloudDevice())) {
                                        $deviceObserver->getICloudDevice()->setProcessing(1);
                                        
                                    } else $deviceObserver->getICloudDevice()->setLastError($queueManager->getError());
                                    $deviceObserver->getICloudDevice()->save();
                                });
                            
                            try {
                                if($deviceObserver->addICloudDevice()){
                                    $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_FINISH, array(
                                        'deviceId' => $deviceObserver->getDevice()->getId()
                                    )));
                                } else throw new \Exception("USER {$this->auth['id']} Can't add ICloudDevice {$deviceObserver->getDevice()->getId()} to License {$this->getLicense()->getId()}");
                                
                            } catch (\Exception $e) {
                                $this->logger->addCritical($e);
                                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Something Was Wrong. Please Contact Us!'));
                                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_REGISTER));
                            }
                        }
                    }
                    $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Invalid Device!'));
                }

                //$this->view->title = $this->di->getTranslator()->_('Choose iCloud Device');
                $this->view->appleID = $_POST['email'];
                $this->view->applePassword = $_POST['password'];
                $this->view->devices = $devices;
                $this->setView('wizard/register.icloud.device.htm');
                return;
            }
        } catch (AuthorizationException $e) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Invalid Apple ID or password'));
            $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_REGISTER));
        } catch (\Exception $e) {
            $this->logger->addCritical($e);
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Unexpected Error. Please try later or contact us!'));
            $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_REGISTER));
        }

        //$this->view->title = $this->di->getTranslator()->_('iCloud Account');
        $this->setView('wizard/register.icloud.account.htm');
    }
    
    public function finishAction()
    {
        $device = $this->getDevice(@$_GET['deviceId']);
        
        if($_POST){
            if(isset($_POST['deviceName']) && strlen($_POST['deviceName']) > 2){
                $device->setName($_POST['deviceName']);
                $device->save();
                $this->redirect($this->di->getRouter()->getRouteUrl('setDevice', array('devId'=>$device->getId())));
            } else {
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Invalid Device Name'));
                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_FINISH, array('deviceId'=>$device->getId())));
            }
        }

        $this->view->device = $device;
        $this->setView('wizard/finish.htm');
    }


    
    
    
    
    
    
    
    


    public function getDataDb($devId)
    {
        if ($this->di['config']['environment'] == 'production') {
            $dbConfig =  GlobalSettings::getDeviceDatabaseConfig($devId);
            
        } else $dbConfig = $this->di['config']['dataDb'];

        return new \PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']}", $dbConfig['username'], $dbConfig['password'], array(
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'set names utf8;',
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ));
    }
    
    protected function getDevice($devId)
    {
        static $device;
        if(is_null($device))
            try {
                $device = new DeviceRecord($this->di->get('db'));
                $device->load($devId);
                if($device->getUserId() != $this->auth['id'] || $device->getDeleted())
                    throw new DeviceNotFoundException;

            } catch (DeviceNotFoundException $e) {
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Device not found'));
                if($this->di->getRouter()->getFindRoute()->isCurrentStep(WizardRouter::STEP_FINISH))
                    $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PACKAGE));
                elseif($this->di->getRouter()->getFindRoute()->isCurrentStep(WizardRouter::STEP_SETUP))
                    $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_SETUP));
            }
        return $device;
    }
    
    protected function getNewDeviceCode(LicenseRecord $license = null)
    {
        static $code;
        if(is_null($code)) {
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
        if(is_null($platform)){
            $platform = $this->getParam('platform');
            if(!in_array($platform, array('android', 'ios', 'icloud'))){
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Invalid Platform'));
                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PLATFORM, array(
                    'licenseId' => $this->getParam('licenseId')
                )));
            }
        }
        return $platform;
    }

    protected function getLicense($mastBeAvailable = true)
    {
        static $license;
        if(is_null($license)) {
            try {
                $license = new LicenseRecord($this->di->get('db'));
                $license->load($this->getParam('licenseId'));
                if($license->getUserId() != $this->auth['id']){
                    throw new LicenseNotFoundException;
                    
                } elseif($mastBeAvailable && $license->getStatus() != LicenseRecord::STATUS_AVAILABLE){
                    $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('License is not available'));
                    $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PACKAGE, array(
                        'licenseId' => false
                    )));
                }

            } catch (LicenseNotFoundException $e) {
                $this->di->getFlashMessages()->add(FlashMessages::ERROR, $this->di->getTranslator()->_('Invalid License'));
                $this->redirect($this->di->getRouter()->getRouteUrl(WizardRouter::STEP_PACKAGE, array(
                    'licenseId' => false
                )));
            }
        }
        return $license;
    }

    public function getParam($name)
    {
        if(isset($this->params[$name]))
            return $this->params[$name];
        else return null;
    }
    
} 