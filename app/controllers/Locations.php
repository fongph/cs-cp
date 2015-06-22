<?php

namespace Controllers;

use Models\Modules,
    CS\Devices\Limitations,
    CS\ICloud\Locations as LocationsService,
    Models\Cp\Zones,
    System\FlashMessages;

class Locations extends BaseModuleController
{

    protected $module = Modules::LOCATIONS;
    private $buildMenu = true;

    protected function init()
    {
        parent::init();
        $this->initCP();
    }

    public function indexAction()
    {
        if ($this->di['currentDevice']['os'] == 'icloud') {
            $this->icloud();
        } else {
            $locations = new \Models\Cp\Locations($this->di);

            if ($this->getRequest()->isAjax()) {
                $this->makeJSONResponse($locations->getDayPoints($this->di['devId'], $this->getRequest()->get('dayStart', 0)));
            }

            $this->view->startTime = $locations->getLastPointTimestamp($this->di['devId']);
            $this->view->hasZones = $locations->hasZones($this->di['devId']);

            $this->setView('cp/locations/index.htm');
        }
    }

    public function setupAction()
    {
        if ($this->di['currentDevice']['os'] !== 'icloud') {
            $this->redirect($this->di['router']->getRouteUrl('locations'));
        }

        $locations = new \Models\Cp\Locations($this->di);
        if ($locations->getDeviceLocationServiceCredentials($this->di['devId']) !== false) {
            $this->redirect($this->di['router']->getRouteUrl('locations'));
        }

        if ($this->params['step'] == 'init') {
            return $this->setupInit();
        } elseif ($this->params['step'] == 'deviceConnection') {
            $this->setupDevicesConnection();
        }

        $this->view->step = $this->params['step'];

        $this->buildMenu = false;
        $this->setLayout('index.htm');
        $this->setView('cp/locations/icloudSetup.htm');
    }

    public function icloud()
    {
        if ($this->di['currentDevice']['os'] !== 'icloud') {
            $this->redirect($this->di['router']->getRouteUrl('locations'));
        }

        $locations = new \Models\Cp\Locations($this->di);

        if (($credential = $locations->getDeviceLocationServiceCredentials($this->di['devId'])) === false) {
            return $this->setupInit();
        }

        $this->view->lastPoint = $locations->getLastPoint($this->di['devId']);

        if ($this->getRequest()->isAjax()) {
            try {
                $data = LocationsService::getDeviceLocationData($credential['apple_id'], $credential['apple_password'], $credential['location_device_hash']);
                $data['success'] = true;

                $locations->addLocationValue($this->di['devId'], $data['timestamp'], $data['latitude'], $data['longitude'], $data['accuracy']);
            } catch (LocationsService\Exceptions\AuthorizationException $e) {
                $data = array(
                    'success' => false,
                    'message' => $this->di['t']->_('iCloud Authorization Error. Please %1$schange the password%2$s and try again.', array(
                        '<a href="/profile/iCloudPassword?deviceId=' . $this->di['devId'] . '">',
                        '</a>'
                    ))
                );
            } catch (LocationsService\Exceptions\DeviceNotFoundException $e) {
                $data = array(
                    'success' => false,
                    'type' => 'fmi-disabled'
                );
            } catch (LocationsService\Exceptions\TrackingException $e) {
                $data = array(
                    'success' => false,
                    'type' => 'location-disabled'
                );
            } catch (LocationsService\Exceptions\TrackingWaitingException $e) {
                $data = array(
                    'success' => false,
                    'type' => 'no-location-data'
                );
            } catch (LocationsService\Exceptions\LocationsException $e) {
                $this->getDI()->get('logger')->addError('iCloud location fail!', array('exception' => $e));
                $data = array(
                    'success' => false,
                    'type' => 'undefined'
                );
            }

            $this->makeJSONResponse($data);
        }

        $this->setView('cp/locations/icloudIndex.htm');
    }

    public function zonesAction()
    {
        $zonesModel = new Zones($this->di);

        if ($this->getRequest()->isAjax()) {
            $this->makeJSONResponse($zonesModel->getMapZonesList($this->di['devId']));
        }

        if ($this->getRequest()->hasGet('delete')) {
            $this->checkDemo($this->di['router']->getRouteUrl('locationsZones'));

            $id = $this->getRequest()->get('delete');
            if ($zonesModel->canDeleteZone($this->di['devId'], $id) === false) {
                $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('Geo-fence was not found.'));
                $this->redirect($this->di['router']->getRouteUrl('locationsZones'));
            }

            $zonesModel->deleteZone($this->di['devId'], $id);
            $this->getDI()->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di['t']->_('Geo-fence has been successfully deleted.'));
            $this->redirect($this->di['router']->getRouteUrl('locationsZones'));
        }

        $this->view->zones = $zonesModel->getZonesList($this->di['devId']);
        $this->setView('cp/locations/zoneList.htm');
    }

    public function zoneAddAction()
    {
        $zonesModel = new Zones($this->di);

        if ($zonesModel->getDeviceZonesCount($this->di['devId']) >= Zones::$countLimit) {
            $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('One does not simply add more than 200 Geo-fences.'));
            $this->redirect($this->di['router']->getRouteUrl('locationsZones'));
        }

        $this->view->triggerList = $zonesModel->getTrigerList();

        $zoneData = $this->getRequest()->post('zoneData', '');
        $scheduleData = $this->getRequest()->post('scheduleData', '');
        $name = $this->getRequest()->post('name', '');
        $trigger = $this->getRequest()->post('trigger', Zones::TRIGGER_ENTER);
        $emailAlert = $this->getRequest()->post('email-alert', '');
        $smsAlert = $this->getRequest()->post('sms-alert', '');
        $enable = $this->getRequest()->post('enable', 1);

        if ($this->getRequest()->hasPost('zoneData', 'name', 'trigger', 'enable')) {
            $this->checkDemo($this->di['router']->getRouteUrl('locationsZonesAdd'));

            if (!Zones::validateName($name)) {
                $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('The name for the Geo-fence is required and should be no longer than 17 symbols.'));
            } else if (!strlen($zoneData)) {
                $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('Geo-fence zone is required. Tap on map and draw a zone to monitor.'));
            } else if (!Zones::validateZoneData($zoneData)) {
                $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('Invalid Geo-fence!'));
            } else {
                if (strlen($scheduleData)) {
                    $schedule = Zones::schedulesToRecurrenceList($scheduleData);
                } else {
                    $schedule = '';
                }

                $zonesModel->addZone($this->di['devId'], $zoneData, $name, $trigger, $emailAlert, $smsAlert, $schedule, $enable);

                $this->getDI()->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di['t']->_('Geo-fence has been successfully added.'));
                $this->redirect($this->di['router']->getRouteUrl('locationsZones'));
            }
        }

        $this->view->zoneData = $zoneData;
        $this->view->scheduleData = $scheduleData;
        $this->view->name = $name;
        $this->view->triggerListSelected = $trigger;
        $this->view->emailNotification = $emailAlert;
        $this->view->smsNotification = $smsAlert;
        $this->view->enable = $enable;
        $this->view->edit = false;


        $this->setView('cp/zone.htm');
    }

    public function zoneEditAction()
    {
        $zonesModel = new Zones($this->di);

        if (($data = $zonesModel->getDeviceZone($this->di['devId'], $this->params['id'])) === false) {
            $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('Geo-fence was not found.'));
            $this->redirect($this->di['router']->getRouteUrl('locationsZones'));
        }

        $this->view->triggerList = $zonesModel->getTrigerList();

        $zoneData = $this->getRequest()->post('zoneData', $data['latitude'] . '|' . $data['longitude'] . '|' . $data['radius']);
        $scheduleData = $this->getRequest()->post('scheduleData', Zones::recurrenceListToSchedules($data['schedule']));
        $name = $this->getRequest()->post('name', $data['name']);
        $trigger = $this->getRequest()->post('trigger', $data['trigger']);
        $enable = $this->getRequest()->post('enable', $data['enable']);

        if ($this->getRequest()->isPost()) {
            $emailAlert = $this->getRequest()->post('email-alert', 0);
            $smsAlert = $this->getRequest()->post('sms-alert', 0);
        } else {
            $emailAlert = $this->getRequest()->post('email-alert', $data['email_alert']);
            $smsAlert = $this->getRequest()->post('sms-alert', $data['sms_alert']);
        }

        if ($this->getRequest()->hasPost('zoneData', 'name', 'trigger', 'enable')) {
            $this->checkDemo($this->di['router']->getRouteUrl('locationsZonesEdit', array('id' => $this->params['id'])));

            if (!Zones::validateName($name)) {
                $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('The name for the Geo-fence is required and should be no longer than 17 symbols.'));
            } else if (!strlen($zoneData)) {
                $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('Geo-fence zone is required. Tap on map and draw a zone to monitor.'));
            } else if (!Zones::validateZoneData($zoneData)) {
                $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('Invalid Geo-fence!'));
            } else {
                if (strlen($scheduleData)) {
                    $schedule = Zones::schedulesToRecurrenceList($scheduleData);
                } else {
                    $schedule = '';
                }

                $zonesModel->updateZone($this->params['id'], $this->di['devId'], $zoneData, $name, $trigger, $emailAlert, $smsAlert, $schedule, $enable);

                $this->getDI()->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di['t']->_('Geo-fence has been successfully updated.'));
                $this->redirect($this->di['router']->getRouteUrl('locationsZones'));
            }
        }

        $this->view->zoneData = $zoneData;
        $this->view->scheduleData = $scheduleData;
        $this->view->name = $name;
        $this->view->triggerListSelected = $trigger;
        $this->view->emailNotification = $emailAlert;
        $this->view->smsNotification = $smsAlert;
        $this->view->enable = $enable;
        $this->view->edit = true;

        $this->setView('cp/locations/zone.htm');
    }
    
    private function setupInit()
    {
        if ($this->getRequest()->isAjax()) {
            $locations = new \Models\Cp\Locations($this->di);

            try {
                $result = $locations->autoAssigniCloudDevice($this->di['devId']);
            } catch (\CS\ICloud\Locations\Exceptions\AuthorizationException $e) {
                $this->makeJSONResponse(array(
                    'success' => false,
                    'message' => $this->di['t']->_('iCloud Authorization Error. Please %1$schange the password%2$s and try again.', array(
                        '<a href="/profile/iCloudPassword?deviceId=' . $this->di['devId'] . '">',
                        '</a>'
                    ))
                ));
            } catch (\CS\ICloud\Locations\Exceptions\LocationsException $e) {
                $this->getDI()->get('logger')->addError('iCloud location autoasign fail', array('exception' => $e));
                $this->makeJSONResponse(array(
                    'success' => false,
                    'message' => $this->di['t']->_('Undefined error! Please %1$scontact support%2$s!', array(
                        '<a href="' . $this->di->getRouter()->getRouteUrl('support') . '">',
                        '</a>'
                    ))
                ));
            }

            if ($result) {
                $this->getDI()->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di['t']->_('Congratulations! The device was successfully connected.'));

                $this->makeJSONResponse(array(
                    'success' => true,
                    'redirectUrl' => $this->di['router']->getRouteUrl(Modules::LOCATIONS)
                ));
            } else {
                $this->makeJSONResponse(array(
                    'success' => false
                ));
            }
        }

        $this->view->step = 'init';
        $this->setView('cp/locations/icloudSetup.htm');
    }

    private function setupDevicesConnection()
    {
        $locations = new \Models\Cp\Locations($this->di);

        if ($this->getRequest()->isAjax()) {
            try {
                $credentials = $locations->getiCloudDeviceCredentials($this->di['devId']);

                if ($this->getRequest()->hasPost('id')) {
                    $locations->assigniCloudDevice($this->di['devId'], $credentials['apple_id'], $credentials['apple_password'], $this->getRequest()->post('id'));

                    $this->getDI()->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di['t']->_('Congratulations! The device was successfully connected.'));

                    $data = array(
                        'success' => true,
                        'redirectUrl' => $this->di['router']->getRouteUrl(Modules::LOCATIONS)
                    );
                } else {
                    $devices = LocationsService::getDevicesList($credentials['apple_id'], $credentials['apple_password']);

                    if (count($devices)) {
                        $data = array(
                            'success' => true,
                            'devices' => $devices
                        );
                    } else {
                        $data = array(
                            'success' => false,
                            'message' => $this->di['t']->_('The device with activated Find My iPhone service was not found. Please make sure whether the target iOS device is connected to the Internet and follow the %1$sguide steps%2$s once again. If it didn\'t work, feel free to contact %3$sPumpic Customer Support%4$s.', array(
                                '<a href="' . $this->di->getRouter()->getRouteUrl('locationsSetup', array('step' => 'instructions')) . '">',
                                '</a>',
                                '<a href="' . $this->di->getRouter()->getRouteUrl('support') . '">',
                                '</a>'
                            ))
                        );
                    }
                }
            } catch (LocationsService\Exceptions\AuthorizationException $e) {
                $data = array(
                    'success' => false,
                    'message' => $this->di['t']->_('iCloud Authorization Error. Please %1$schange the password%2$s and try again.', array(
                        '<a href="/profile/iCloudPassword?deviceId=' . $this->di['devId'] . '">',
                        '</a>'
                    ))
                );
            } catch (LocationsService\Exceptions\DeviceNotAddedException $e) {
                $data = array(
                    'success' => false,
                    'message' => $this->di['t']->_('Error connecting device. Please make sure whether the target iOS device is connected to the Internet and follow the %1$sguide steps%2$s once again. If it didn’t work, feel free to contact %3$sPumpic Customer Support%4$s.', array(
                        '<a href="' . $this->di->getRouter()->getRouteUrl('locationsSetup', array('step' => 'instructions')) . '">',
                        '</a>',
                        '<a href="' . $this->di->getRouter()->getRouteUrl('support') . '">',
                        '</a>'
                    ))
                );
            } catch (LocationsService\Exceptions\LocationsException $e) {
                $this->getDI()->get('logger')->addError('iCloud location fail', array('exception' => $e));
                $data = array(
                    'success' => false,
                    'message' => $this->di['t']->_('Undefined error! Please %1$scontact support%2$s!', array(
                        '<a href="' . $this->di->getRouter()->getRouteUrl('support') . '">',
                        '</a>'
                    ))
                );
            }

            $this->makeJSONResponse($data);
        }
    }

    protected function postAction()
    {
        parent::postAction();

        if ($this->buildMenu) {
            $this->buildCpMenu();
        }

        /**
         * @deprecated until there are no applications with old version
         */
        if (($this->di['currentDevice']['os'] === 'android' && $this->di['currentDevice']['app_version'] < 5) ||
                ($this->di['currentDevice']['os'] === 'ios' && $this->di['currentDevice']['app_version'] < 3)) {
            $this->view->showUpdateBlock = $this->di['currentDevice']['os'];
        }

        $this->view->title = $this->di['t']->_('View Locations');
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::GPS);
    }

}
