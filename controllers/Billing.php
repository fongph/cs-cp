<?php

namespace Controllers;

use System\FlashMessages,
    CS\Devices\Manager as DevicesManager;

class Billing extends BaseController
{

    public function indexAction()
    {
        if ($this->getRequest()->isAjax()) {
            $billingModel = new \Models\Billing($this->di);

            $dataTableRequest = new \System\DataTableRequest();
            $dataTableRequest->getRequest($this->getRequest()->get(), array('active'));
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($billingModel->getDataTableData($this->auth['id'], $dataTableRequest->getResult()));
        }

        $this->view->title = $this->di->getTranslator()->_('Payments & Devices');
        $this->view->unlimitedValue = \CS\Models\Limitation\LimitationRecord::UNLIMITED_VALUE;

        $this->setView('billing/index.htm');
    }

    public function assignDeviceAction()
    {
        $devicesManager = new DevicesManager($this->di['db']);
        //$devicesModel = new \Models\Devices($this->di);

        $license = $this->getRequest()->get('license');
        if ($license == null ||
                !$devicesManager->isUserLicenseAvailable($license, $this->auth['id'])) {

            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid license!'));
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        }

        $list = $devicesManager->getUserUnAssignedDevicesList($this->auth['id']);

        if ($this->getRequest()->isPost()) {
            $device = $this->getRequest()->post('device');
            if ($device == null || !in_array($device, array_keys($list))) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid device!'));
            }

            $devicesManager->assignLicenseToDevice($license, $device);
            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Device successfully assigned to your license!'));
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        }

        $this->view->title = $this->di->getTranslator()->_('Assign Device');
        $this->view->devices = $list;
        $this->setView('billing/assignDevice.htm');
    }

}
