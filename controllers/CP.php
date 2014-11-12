<?php

namespace Controllers;

class CP extends BaseController {

    protected function init() {
        parent::init();
    }

    public function mainAction() {
        $this->redirect($this->di['router']->getRouteUrl('calls'));
    }

    public function setDeviceAction() {
        $devicesModel = new \Models\Devices($this->di);
        $devicesModel->setCurrentDevId($this->params['devId']);
        $this->_redirectToLastUsedModule();
    }

    public function upgradeAction() {
        $this->initCP();
        $planes = array('PRO Plus', 'PRO');

        $plan = 'PRO Plus';
        if (isset($_GET['p'], $planes[$_GET['p']])) {
            $plan = $planes[$_GET['p']];
        }

        $devicesModel = new \Models\Devices($this->di);
        if ($this->isPost() && isset($_POST['device'])) {
            $this->redirect($devicesModel->buildUpdateUrl($_POST['device'], $plan, $this->auth['login']));
        }

        $this->view->devicesSelectList = $devicesModel->getDevicesSelectList();
        $this->view->title = $this->di['t']->_('Ready to explore more opportunities?');
        $this->view->plan = $plan;

        $this->setView('cp/upgrade.htm');
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();
    }

    protected function _redirectToLastUsedModule() {
        if (isset($_SERVER['HTTP_REFERER'])) {
            $prevRequestUri = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);

            $this->di['router']->execute($prevRequestUri, function($route, $routeName) {
                foreach ($this->di['config']['cpMenu'] as $key => $value) {
                    if (($routeName == $key) || substr($routeName, 0, strlen($key)) == $key) {
                        $this->redirect($this->di['router']->getRouteUrl($key));
                    }
                }
            }, false);
        }

        $this->redirect($this->di['router']->getRouteUrl('cp'));
    }

}
