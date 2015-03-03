<?php

namespace Controllers;

use Models\Modules,
    CS\Devices\Limitations,
    Models\Cp\Zones,
    System\FlashMessages;

class Locations extends BaseModuleController
{

    protected $module = Modules::LOCATIONS;

    protected function init()
    {
        parent::init();
        $this->initCP();
    }

    private function privateRouter()
    {
        switch ($this->getDI()->getRouter()->getRouteName()) {
            case 'locationsZones':
                $this->zones();
                break;

            case 'locationsZonesAdd':
                $this->zoneAdd();
                break;

            case 'locationsZonesEdit':
                $this->zoneEdit();
                break;

            default:
                $this->locations();
                break;
        }
    }

    private function locations()
    {
        $locations = new \Models\Cp\Locations($this->di);
        
        if ($this->getRequest()->isAjax()) {
            $this->makeJSONResponse($locations->getDayPoints($this->di['devId'], $this->getRequest()->get('dayStart', 0)));
        }
        
        $this->view->startTime = $locations->getLastPointTimestamp($this->di['devId']);
        $this->view->hasZones = $locations->hasZones($this->di['devId']);

        $this->setView('cp/locationsData.htm');
    }

    private function zones()
    {
        $zonesModel = new Zones($this->di);

        if ($this->getRequest()->isAjax()) {
            $this->makeJSONResponse($zonesModel->getMapZonesList($this->di['devId']));
        }
        
        if ($this->getRequest()->hasGet('delete')) {
            $id = $this->getRequest()->get('delete');
            if ($zonesModel->canDeleteZone($this->di['devId'], $id) === false) {
                $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('Geofence not found!'));
                $this->redirect($this->di['router']->getRouteUrl('locationsZones'));
            }

            $zonesModel->deleteZone($this->di['devId'], $id);
            $this->getDI()->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di['t']->_('Geofence successfully deleted!'));
            $this->redirect($this->di['router']->getRouteUrl('locationsZones'));
        }

        $this->view->zones = $zonesModel->getZonesList($this->di['devId']);
        $this->setView('cp/zoneList.htm');
    }

    private function zoneAdd()
    {
        $zonesModel = new Zones($this->di);

        if ($zonesModel->getDeviceZonesCount($this->di['devId']) >= Zones::$countLimit) {
            $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('Geofences limit reached!'));
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
            if (!Zones::validateName($name)) {
                $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('Invalid name!'));
            } else if (!strlen($zoneData)) {
                $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('Geofence not selected!'));
            } else if (!Zones::validateZoneData($zoneData)) {
                $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('Invalid geofence!'));
            } else {
                $schedule = Zones::schedulesToRecurrenceList($scheduleData);
                $zonesModel->addZone($this->di['devId'], $zoneData, $name, $trigger, $emailAlert, $smsAlert, $schedule, $enable);

                $this->getDI()->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di['t']->_('Geofence successfully added!'));
                $this->redirect($this->di['router']->getRouteUrl('locationsZones'));
            }
        }

        $this->view->zoneData = $zoneData;
        $this->view->scheduleData = '';
        $this->view->name = $name;
        $this->view->triggerListSelected = $trigger;
        $this->view->emailNotification = $emailAlert;
        $this->view->smsNotification = $smsAlert;
        $this->view->enable = $enable;

        $this->setView('cp/zone.htm');
    }

    private function zoneEdit()
    {
        $zonesModel = new Zones($this->di);

        if (($data = $zonesModel->getDeviceZone($this->di['devId'], $this->params['id'])) === false) {
            $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('Geofence not found!'));
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
            if (!Zones::validateName($name)) {
                $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('Invalid name!'));
            } else if (!strlen($zoneData)) {
                $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('Geofence not selected!'));
            } else if (!Zones::validateZoneData($zoneData)) {
                $this->getDI()->getFlashMessages()->add(FlashMessages::ERROR, $this->di['t']->_('Invalid geofence!'));
            } else {
                $schedule = Zones::schedulesToRecurrenceList($scheduleData);

                $zonesModel->updateZone($this->params['id'], $this->di['devId'], $zoneData, $name, $trigger, $emailAlert, $smsAlert, $schedule, $enable);

                $this->getDI()->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di['t']->_('Geofence successfully updated!'));
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

        $this->setView('cp/zone.htm');
    }

    public function indexAction()
    {
        if ($this->di['isTestUser']($this->auth['id'])) {
            return $this->privateRouter();
        }

        if ($this->getRequest()->isAjax()) {
            if (!$this->getRequest()->hasPost('date')) {
                return $this->makeJSONResponse(array('success' => 0));
            }

            $locationsModel = new \Models\Cp\Locations($this->di);
            if (($data = $locationsModel->getPoints($this->di['devId'], $this->getRequest()->post('date'))) !== false) {
                return $this->makeJSONResponse(array(
                            'success' => 1,
                            'result' => $data
                ));
            } else {
                return $this->makeJSONResponse(array('success' => 0));
            }

            $dataTableRequest = new \System\DataTableRequest();
            $this->makeJSONResponse($locationsModel->getDataTableData($this->di['devId'], $dataTableRequest->getRequest($this->getRequest()->get())));
        }

        $locationsModel = new \Models\Cp\Locations($this->di);
        $this->view->startTime = $locationsModel->getLastPointTime($this->di['devId']);

        $this->setView('cp/locations.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Locations');
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::GPS);
    }

}
