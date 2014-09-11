<?php

namespace Controllers;

use System\FlashMessages;

class BaseController extends \System\Controller {

    protected $auth = null;
    protected $module = '';

    protected function init() {
        if ($this->di['auth']->hasIdentity()) {
            $this->auth = $this->di['auth']->getIdentity();
        }
    }

    public function error404() {
        header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);
        $this->view->title = $this->di['t']->_('Not Found');
        $this->setView('index/404.htm');
        $this->response();
        die;
    }

    protected function initCP() {
        $devicesModel = new \Models\Devices($this->di);

        $devices = $devicesModel->getDevicesByUser($this->auth['id']);
        $this->di->set('devicesList', $devices);

        $devId = $devicesModel->getCurrentDevId();
        $this->di->set('devId', $devId);
        $this->di->set('currentDevice', $devices[$devId]);

        if ($devId === null) {
            $this->di['flashMessages']->add(FlashMessages::INFO, $this->di['t']->_('No devices have been added to your Control Panel!'));
            $this->redirect($this->di['router']->getRouteUrl('profile'));
        } else if(isset($this->di['config']['cpMenu'][$this->module])) {
            if ($devicesModel->isModuleActive($this->di['config']['cpMenu'][$this->module]) === false) {
                $this->redirect($this->di['router']->getRouteUrl('calls'));
            }

            $this->view->paid = $devicesModel->isPaid($this->module);
            if (!$this->view->paid) {
                if ($this->di['router']->getRouteName() != $this->module) {
                    $this->redirect($this->di['router']->getRouteUrl($this->module));
                }
                
                $this->view->packageLink = $devicesModel->getBuyNowUrl($this->auth['login'], $devId, 'PACKAGE_ID');
            }
        }
    }

    protected function buildCpMenu() {
        $devicesModel = new \Models\Devices($this->di);
        $this->view->cpMenu = array();

        foreach ($this->di['config']['cpMenu'] as $routeName => $data) {
            if (($name = $devicesModel->isModuleActive($data)) !== false) {
                $this->view->cpMenu[$this->di['router']->getRouteUrl($routeName)] = array(
                    'name' => $this->di['t']->_($name),
                    'class' => $routeName,
                    'active' => $routeName == $this->module
                );
            }
        }
    }

    protected function postAction() {
        if ($this->auth) {
            $this->view->authData = $this->auth;
        }
    }

    protected function checkDisplayLength($value = 10) {
        if ($value !== $this->auth['records_per_page']) {
            $usersModel = new \Models\Users($this->di);
            $usersModel->setRecordsPerPage($value);
            $usersModel->reLogin();
        }
    }

}
