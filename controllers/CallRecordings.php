<?php

namespace Controllers;

use System\FlashMessages;

class CallRecordings extends BaseController {

    protected $module = 'callRecordings';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    //TODO: reorganize
    public function indexAction() {
        $callRecordingsModel = new \Models\Cp\CallRecordings($this->di);

        if ($this->isAjaxRequest()) {
            $dataTableRequest = new \System\DataTableRequest();

            $dataTableRequest->getRequest($_GET, array('timeFrom', 'timeTo'));
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($callRecordingsModel->getDataTableData($this->di['devId'], $dataTableRequest->getResult()));
        } else if ($this->isPost()) {
            if (isset($_POST['addPhoneNumber'], $_POST['phone'])) {
                try {
                    if ($callRecordingsModel->addPhoneNumber($this->di['devId'], $_POST['phone']) === false) {
                        $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Error occurred during adding the phone number!'));
                    } else {
                        $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The phone number has already been added to the list!'));
                    }
                } catch (\Models\Cp\CallRecordingInvalidPhoneNumberException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid phone number!'));
                } catch (\Models\Cp\CallRecordingPhoneNumberExistException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Phone number already exist!'));
                }
            } elseif (isset($_POST['recordAll'])) {
                if ($callRecordingsModel->setRecordAllPhones($this->di['devId'])) {
                    $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The Call Recording option has been enabled. All the calls will be recorded!'));
                } else {
                    $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('The device is already set to record all calls!'));
                }
            } else
            if (isset($_POST['network'])) {
                $devicesModel = new \Models\Devices($this->di);
                $devicesModel->setNetwork($this->di['devId'], 'callRecordings', $_POST['network']);
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The changes have been successfully updated!'));
            }

            $this->redirect($this->di['router']->getRouteUrl('callRecordings'));
        } else if (isset($_GET['removePhoneNumber'])) {
            try {
                if ($callRecordingsModel->removePhoneNumber($this->di['devId'], $_GET['removePhoneNumber']) === false) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Error occurred during deleting the phone number!'));
                } else {
                    $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The phone number has been successfully deleted from the list!'));
                }
            } catch (\Models\Cp\CallRecordingInvalidPhoneNumberException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid phone number!'));
            } catch (\Models\Cp\SettingsPhoneNumberNotFoundInListException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The phone number has not been found!'));
            }

            $this->redirect($this->di['router']->getRouteUrl('callRecordings'));
        } else if (isset($_GET['play'])) {
            $url = $callRecordingsModel->getPlayUrl($this->di['devId'], $_GET['play']);
            $this->redirect($url);
        } else if (isset($_GET['download'])) {
            $url = $callRecordingsModel->getDownloadUrl($this->di['devId'], $_GET['download']);
            $this->redirect($url);
        }

        if ($this->view->paid) {
            $devicesModel = new \Models\Devices($this->di);

            $this->view->network = $devicesModel->getNetwork($this->di['devId'], 'callRecordings');
            $this->view->networksList = \Models\Devices::$networksList;
            $this->view->phoneNumbersList = $callRecordingsModel->getPhoneNumbersList($this->di['devId']);
            $this->view->hasRecords = $callRecordingsModel->hasRecords($this->di['devId']);
        }

        $this->setView('cp/callRecordings.htm');
    }

    public function deleteAction() {
        $callRecordingsModel = new \Models\Cp\CallRecordings($this->di);
        
        try {
            $callRecordingsModel->delete($this->di['devId'], $this->params['value']);
            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The call recording has been successfully deleted!'));
        } catch (\Models\Cp\CallRecordingNotFoundException $e) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The call recording you want to delete has not been found!'));
        }

        $this->redirect($this->di['router']->getRouteUrl('callRecordings'));
    }

    public function playAction() {
        $callRecordingsModel = new \Models\Cp\CallRecordings($this->di);
        $url = $callRecordingsModel->getPlayUrl($this->di['devId'], $this->params['value']);
        $this->redirect($url);
    }

    public function downloadAction() {
        $callRecordingsModel = new \Models\Cp\CallRecordings($this->di);
        $url = $callRecordingsModel->getDownloadUrl($this->di['devId'], $this->params['value']);
        $this->redirect($url);
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Call Recordings');
    }

}
