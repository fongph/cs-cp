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

    public function indexAction()
    {
        $locations = new \Models\Cp\Locations($this->di);

        if ($this->getRequest()->isAjax()) {
            $this->makeJSONResponse($locations->getDayPoints($this->di['devId'], $this->getRequest()->get('dayStart', 0)));
        }

        $this->view->startTime = $locations->getLastPointTimestamp($this->di['devId']);
        $this->view->hasZones = $locations->hasZones($this->di['devId']);

        $this->setView('cp/locations.htm');
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
        $this->setView('cp/zoneList.htm');
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

        $this->setView('cp/zone.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

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
