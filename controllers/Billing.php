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

            $dataTableRequest = new \System\DataTableRequest($this->di);

            $data = $billingModel->getDataTableData(
                    $this->auth['id'], $dataTableRequest->buildResult(array('active'))
            );
            $this->checkDisplayLength($dataTableRequest->getDisplayLength());
            $this->makeJSONResponse($data);
        }

        $this->view->title = $this->di->getTranslator()->_('Payments & Devices');
        $this->view->unlimitedValue = \CS\Models\Limitation\LimitationRecord::UNLIMITED_VALUE;

        $this->setView('billing/index.htm');
    }

    public function assignDeviceAction()
    {
        $devicesManager = new DevicesManager($this->di['db']);

        $license = $this->getRequest()->get('license');
        if ($license == null ||
                !$devicesManager->isUserLicenseAvailable($license, $this->auth['id'])) {

            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid license!'));
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        }

        $list = $devicesManager->getUserUnAssignedDevicesList($this->auth['id']);

        if (!count($list)) {
            $this->redirect($this->di['router']->getRouteUrl('billingAddDevice') . '?license=' . $license);
        }

        $device = $this->getRequest()->get('device');
        if ($device !== null) {
            if (!in_array($device, array_keys($list))) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid device!'));
            }

            $devicesManager->assignLicenseToDevice($license, $device);
            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('Device successfully assigned to your license!'));

            $licInfo = (new \Models\Billing($this->di))
                ->getLicenseDeviceInfo($license);

            if($licInfo !== false){
                (new \Models\Users($this->di))
                    ->addSystemNote($this->auth['id'],
                        "Assign {$licInfo['product_name']} to device {$licInfo['device_name']} " . json_encode(array(
                            'device_id' => $licInfo['dev_id'],
                            'license_id' => $licInfo['license_id']
                        ))
                    );
            }
            
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        }

        $this->view->title = $this->di->getTranslator()->_('Assign Device');
        $this->view->devices = $list;
        $this->view->license = $license;
        $this->setView('billing/assignDevice.htm');
    }

    public function addDeviceAction()
    {
        $this->view->title = $this->di->getTranslator()->_('New Device');

        $license = $this->getRequest()->get('license');

        $devicesManager = new DevicesManager($this->di['db']);

        if ($this->getRequest()->hasGet('code')) {
            $this->completeAddDevice($devicesManager);
        }

        if ($license != null &&
                !$devicesManager->isUserLicenseAvailable($license, $this->auth['id'])) {

            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid license!'));
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        }

        try {
            $code = $devicesManager->getUserDeviceAddCode($this->auth['id'], $license);
        } catch (CS\Devices\Manager\DeviceCodeGenerationException $e) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, "Error during add device! Pleace try again later!");
            $this->di['logger']->addCritical("Device code generation failed!");
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        }

        $this->view->code = $code;
        $this->view->displayCode = str_pad($code, 4, '0', STR_PAD_LEFT);

        $this->setView('billing/addDevice.htm');
    }

    private function completeAddDevice(DevicesManager $devicesManager)
    {
        $code = $this->getRequest()->get('code');
        $info = $devicesManager->getUserAddCodeInfo($this->auth['id'], $code);

        if ($info === false) {
            $this->di->getFlashMessages()->add(FlashMessages::ERROR, "Code not found!");
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        }

        if ($info['assigned']) {
            $this->di->getFlashMessages()->add(FlashMessages::SUCCESS, "Your device successfully added!");
            $this->redirect($this->di['router']->getRouteUrl('billing'));
        }
        
        if ($info['expired']) {
            $this->di->getFlashMessages()->add(FlashMessages::INFO, "Code was expired. We've generated new code for you. Please enter it on mobile phone.");
        } else {
            $this->di->getFlashMessages()->add(FlashMessages::INFO, "It looks you haven't entered code on mobile yet. Please do it now.");
        }

        if ($info['license_id'] !== null) {
            $this->redirect($this->di['router']->getRouteUrl('billingAddDevice') . '?license=' . $info['license_id']);
        } else {
            $this->redirect($this->di['router']->getRouteUrl('billingAddDevice'));
        }
    }

}
