<?php

namespace Controllers;

use Models\Modules,
    CS\Devices\Limitations,
    CS\ICloud\Locations as LocationsService,
    Models\Cp\Zones,
    System\FlashMessages;
use Components\CloudDeviceManager\AppleCloudDeviceManager;

class Locations extends BaseModuleController {

    protected $module = Modules::LOCATIONS;
    private $buildMenu = true;

    protected function init()
    {
        parent::init();
        $this->initCP();

        $this->exportEnabled = ($this->auth['id'] == 317);
    }

    public function indexAction()
    {
        $locations = new \Models\Cp\Locations($this->di);

        if ($this->di['currentDevice']['os'] == 'icloud') {
            $this->iCloud($locations);
        }

        if (($this->di['currentDevice']['os'] === 'android' && $this->di['currentDevice']['app_version'] > 6) ||
                ($this->di['currentDevice']['os'] === 'ios' && $this->di['currentDevice']['app_version'] > 5)) {

            $settingsModel = new \Models\Cp\Settings($this->di);
            $settings = $settingsModel->getDeviceSettings($this->di['devId']);
            $this->view->serviceLocation = $settings['location_service_enabled'];
        }

        if ($this->getRequest()->isAjax() && $this->getRequest()->hasGet('dayStart')) {
            $data = $locations->getDayPoints($this->di['devId'], $this->getRequest()->get('dayStart', 0));

            $this->makeJSONResponse($data);
        }

        $this->view->startTime = $locations->getLastPointTimestamp($this->di['devId']);
        $this->view->hasZones = $locations->hasZones($this->di['devId']);
        $this->view->exportEnabled = $this->exportEnabled;

        $this->setView('cp/locations/index.htm');
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

    public function exportAction()
    {
        if (!$this->exportEnabled) {
            $this->redirect($this->di['router']->getRouteUrl('locations'));
        }

        $locations = new \Models\Cp\Locations($this->di);

        $zones = $locations->getZonesNames($this->di['devId']);

        if (count($zones) == 0) {
            $this->di->getFlashMessages()->add(FlashMessages::INFO, $this->di['t']->_('No zones for report!'));
            $this->redirect($this->di->getRouter()->getRouteUrl('locations'));
        }

        if ($this->getRequest()->isPost()) {
            $zonesList = $this->getRequest()->post('zones');
            $timeFrom = $this->getRequest()->post('timeFrom');
            $timeTo = $this->getRequest()->post('timeTo');

            if (empty($zonesList) || !is_array($zonesList)) {
                $this->di->getFlashMessages()->add(FlashMessages::INFO, $this->di['t']->_('At least one zone must be selected!'));
            } else {
                $result = $locations->getPointsForExport($this->di['devId'], $zonesList, $timeFrom, $timeTo);
                $this->createExcelReport($result);
            }
        }

        $this->view->zonesList = $zones;
        $this->setView('cp/locations/export.htm');
    }

    public function createExcelReport(array $result)
    {
        $objPHPExcel = new \PHPExcel();

        $objPHPExcel->setActiveSheetIndex(0);
        $activeSheet = $objPHPExcel->getActiveSheet();
        $activeSheet->setCellValue('A1', 'Geo-fences Name');
        $activeSheet->setCellValue('B1', 'Date');
        $activeSheet->setCellValue('C1', 'Location');
        $activeSheet->setCellValue('D1', 'Type');
        $activeSheet->setCellValue('E1', 'Address');
        $activeSheet->setCellValue('F1', 'E-mail notification');

        $rowStart = 2;
        $i = 0;
        foreach ($result as $item) {
            $rowNext = $rowStart + $i;
            $googleMapsLink = 'http://maps.google.com/maps?q=' . $item['latitude'] . ',' . $item['longitude'];
            $dateTime = date("F j, Y - g:i a", $item['timestamp']);
            $activeSheet->setCellValue('A' . $rowNext, $item['zone']);
            $activeSheet->setCellValue('B' . $rowNext, $dateTime);
            $activeSheet->setCellValue('C' . $rowNext, $googleMapsLink);
            $activeSheet->setCellValue('D' . $rowNext, $item['type']);
            $activeSheet->setCellValue('E' . $rowNext, $item['address']);
            $activeSheet->setCellValue('F' . $rowNext, $item['email_notified']);

            $i++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="report.xlsx"');
        header('Cache-Control: max-age=0');

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        die;
    }

    private function iCloud(\Models\Cp\Locations $locations)
    {
        $credentials = $locations->getDeviceLocationServiceCredentials($this->di['devId']);

        if ($this->getRequest()->isAjax() && $this->getRequest()->hasGet('currentLocation')) {
            $data = $locations->getCloudLocation($this->auth['id'], $this->di['devId'], $credentials);
            $this->makeJSONResponse($data);
        }

        if ($credentials === false) {
            $this->redirect($this->di->getRouter()->getRouteUrl('locationsSetup', array('step' => 'init')));
        }
    }

    public function zonesAction()
    {
        if ($this->di['currentDevice']['os'] == 'icloud') {
            $this->redirect($this->di['router']->getRouteUrl('locations'));
        }

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
        if ($this->di['currentDevice']['os'] == 'icloud') {
            $this->redirect($this->di['router']->getRouteUrl('locations'));
        }

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


        $this->setView('cp/locations/zone.htm');
    }

    public function zoneEditAction()
    {
        if ($this->di['currentDevice']['os'] == 'icloud') {
            $this->redirect($this->di['router']->getRouteUrl('locations'));
        }

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
                $locations->autoAssigniCloudDevice($this->di['devId'], $this->auth['id']);
            } catch (\Components\CloudDeviceManager\Exception\BadCredentialsException $e) {
                $this->makeJSONResponse([
                    'success' => false,
                    'message' => $this->di['t']->_('iCloud Authorization Error. Please %1$supdate the password in our system%2$s and try again.', [
                        '<a href="/profile/iCloudPassword/' . $this->di['devId'] . '">',
                        '</a>'
                    ])
                ]);
            } catch (\Components\CloudDeviceManager\Exception\TwoFactorAuthenticationRequiredException $e) {
                $this->makeJSONResponse([
                    'success' => false,
                    'message' => $this->di['t']->_('iCloud Authorization Error. Please %1$supdate the password in our system%2$s and try again.', [
                        '<a href="/profile/iCloudPassword/' . $this->di['devId'] . '">',
                        '</a>'
                    ])
                ]);
            } catch (\Components\CloudDeviceManager\Exception\AccountLockedException $e) {
                $this->makeJSONResponse([
                    'success' => false,
                    'message' => $this->di['t']->_('Find My iPhone Authentication error. To continue using "Locations" feature, please, unblock the target Apple ID and %1$svalidate iCloud account in our system%2$s.', [
                        '<a href="/profile/iCloudPassword/' . $this->di['devId'] . '">',
                        '</a>'
                    ])
                ]);
            } catch (\Components\CloudDeviceManager\Exception\DeviceLocationNotDetectedException $e) {
                $this->makeJSONResponse(array(
                    'success' => false
                ));
            } catch (\Exception $e) {
                $this->getDI()->get('logger')->addError('iCloud location autoasign fail', array('exception' => $e));

                $this->makeJSONResponse([
                    'success' => false,
                    'message' => $this->di['t']->_('Undefined error! Please %1$scontact support%2$s!', [
                        '<a href="' . $this->di->getRouter()->getRouteUrl('support') . '">',
                        '</a>'
                    ])
                ]);
            }

            $this->getDI()->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di['t']->_('Congratulations! The device was successfully connected.'));

            $this->makeJSONResponse([
                'success' => true,
                'redirectUrl' => $this->di['router']->getRouteUrl(Modules::LOCATIONS)
            ]);
        }

        $this->view->step = 'init';
        $this->setView('cp/locations/icloudSetup.htm');
    }

    private function setupDevicesConnection()
    {
        $locations = new \Models\Cp\Locations($this->di);

        if ($this->getRequest()->isAjax()) {
            try {
                $credentials = $locations->getCloudDeviceCredentials($this->di['devId']);

                if ($this->getRequest()->hasPost('id')) {
                    $locations->assigniCloudDevice($this->di['devId'], $credentials['apple_id'], $credentials['apple_password'], $this->getRequest()->post('id'), $this->auth['id']);

                    $this->getDI()->getFlashMessages()->add(FlashMessages::SUCCESS, $this->di['t']->_('Congratulations! The device was successfully connected.'));

                    $data = [
                        'success' => true,
                        'redirectUrl' => $this->di['router']->getRouteUrl(Modules::LOCATIONS)
                    ];
                } else {
                    $devices = $locations->getDevicesList($credentials['apple_id'], $credentials['apple_password']);

                    if (count($devices)) {
                        $data = [
                            'success' => true,
                            'devices' => $devices
                        ];
                    } else {
                        $data = [
                            'success' => false,
                            'message' => $this->di['t']->_('The device with activated Find My iPhone service was not found. Please make sure whether the target iOS device is connected to the Internet and follow the %1$sguide steps%2$s once again. If it didn\'t work, feel free to contact %3$sPumpic Customer Support%4$s.', [
                                '<a href="' . $this->di->getRouter()->getRouteUrl('locationsSetup', array('step' => 'instructions')) . '">',
                                '</a>',
                                '<a href="' . $this->di->getRouter()->getRouteUrl('support') . '">',
                                '</a>'
                            ])
                        ];
                    }
                }
            } catch (\Components\CloudDeviceManager\Exception\BadCredentialsException $e) {
                $data = array(
                    'success' => false,
                    'message' => $this->di['t']->_('iCloud Authorization Error. Please %1$supdate the password in our system%2$s and try again.', array(
                        '<a href="/profile/iCloudAccount/' . $this->di['devId'] . '">',
                        '</a>'
                    ))
                );
            } catch (\Components\CloudDeviceManager\Exception\TwoFactorAuthenticationRequiredException $e) {
                $data = array(
                    'success' => false,
                    'message' => $this->di['t']->_('iCloud Authorization Error. Please %1$supdate the password in our system%2$s and try again.', array(
                        '<a href="/profile/iCloudAccount/' . $this->di['devId'] . '">',
                        '</a>'
                    ))
                );
            } catch (\Components\CloudDeviceManager\Exception\AccountLockedException $e) {
                $locations->setFmipDisabled($this->di['devId'], true);

                return [
                    'success' => false,
                    'message' => $this->di['t']->_('Find My iPhone Authentication error. To continue using "Locations" feature, please, unblock the target Apple ID and %1$svalidate iCloud account in our system%2$s.', [
                        '<a href="/profile/iCloudPassword/' . $this->di['devId'] . '">',
                        '</a>'
                    ])
                ];
            } catch (\Exception $e) {
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

        $this->view->title = $this->di['t']->_('Locations');

        if ($this->di['currentDevice']['os'] != 'icloud') {
            $this->view->customTimezoneOffset = 0;
        }
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::GPS);
    }

}
