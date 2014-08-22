<?php

namespace Controllers;

use System\FlashMessages;

class Surrounding extends BaseController {

    protected $module = 'surrounding';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        $surroundingsModel = new \Models\Cp\Surroundings($this->di);
        if ($this->isAjaxRequest()) {
            $dataTableRequest = new \System\DataTableRequest();

            $dataTableRequest->getRequest($_GET);
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($surroundingsModel->getDataTableData($this->di['devId'], $dataTableRequest->getResult()));
        } else if ($this->isPost()) {
            if (isset($_POST['addSurrounding'], $_POST['timestamp'], $_POST['duration'])) {
                try {
                    $surroundingsModel = new \Models\Cp\Surroundings($this->di);

                    if ($surroundingsModel->addSurroundingTask($this->di['devId'], $_POST['timestamp'], $_POST['duration'])) {
                        $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Surrounding record request has been saved!'));
                    } else {
                        $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Error occurred during sending the request!'));
                    }
                } catch (\Models\Cp\SurroundingStartTimeInvalidException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid start time. Start time should be set for at least 20 minutes ahead!'));
                } catch (\Models\Cp\SurroundingStartTimeInvalidException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid duration of the surrounding record!'));
                } catch (\Models\Cp\SurroundingStartTimeInvalidException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Surroundings record intervals overlap!'));
                } catch (\Models\Cp\SurroundingLimitReachedException $e) {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('You have reached the limit of recordings on your subscription plan.'));
                }
            } elseif (isset($_POST['network'])) {
                $devicesModel = new \Models\Devices($this->di);
                $devicesModel->setNetwork($this->di['devId'], 'surrounding', $_POST['network']);
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The changes have been successfully updated!'));
            }

            $this->redirect($this->di['router']->getRouteUrl('surrounding'));
        }

        if ($this->view->paid) {
            $devicesModel = new \Models\Devices($this->di);
            $this->view->network = $devicesModel->getNetwork($this->di['devId'], 'surrounding');
            $this->view->networksList = \Models\Devices::$networksList;
            $this->view->maxAwaitingTime = $surroundingsModel->getMaxAwaitingTime($this->di['devId']);
            $this->view->hasRecords = $surroundingsModel->hasRecords($this->di['devId']);
        }

        $this->setView('cp/surroundings.htm');
    }

    public function deleteAction() {
        $surroundingsModel = new \Models\Cp\Surroundings($this->di);
        if ($surroundingsModel->delete($this->di['devId'], $this->params['value'])) {
            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Surrounding record has been successfully deleted!'));
        } else {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Surrounding record you want to delete does not exist any longer!'));
        }

        $this->redirect($this->di['router']->getRouteUrl('surrounding'));
    }

    public function playAction() {
        $surroundingsModel = new \Models\Cp\Surroundings($this->di);
        $url = $surroundingsModel->getPlayUrl($this->di['devId'], $this->params['value']);
        $this->redirect($url);
    }

    public function downloadAction() {
        $surroundingsModel = new \Models\Cp\Surroundings($this->di);
        $url = $surroundingsModel->getDownloadUrl($this->di['devId'], $this->params['value']);
        $this->redirect($url);
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Surrounding Records');
    }

}
