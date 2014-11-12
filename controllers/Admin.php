<?php

namespace Controllers;

use \System\FlashMessages;
use \Models\Users;

class Admin extends BaseController {

    public function loginAction() {
        if (isset($_GET['id'], $_GET['h'])) {
            $usersModel = new Users($this->di);

            if (isset($_GET['device'])) {
                if ($usersModel->simpleLogin($_GET['id'], $_GET['h'], $_GET['device'])) {
                    $devicesModel = new \Models\Devices($this->di);
                    $devicesModel->setCurrentDevId($_GET['device']);
                    $this->redirect($this->di['router']->getRouteUrl('cp'));
                }
            } else {
                if ($usersModel->simpleLogin($_GET['id'], $_GET['h'])) {
                    $this->redirect($this->di['router']->getRouteUrl('main'));
                }
            }
        }

        $this->error404();
    }

    public function lostPasswordSendAction() {
        if (isset($_GET['id'], $_GET['h'])) {
            $usersModel = new Users($this->di);

            try {
                if ($usersModel->simpleRestorePassword($_GET['id'], $_GET['h'])) {
                    $this->makeJSONResponse(array(
                        'success' => 1
                    ));
                }
            } catch (Exception $e) {}

            $this->makeJSONResponse(array(
                'success' => 0
            ));
        }

        $this->error404();
    }

    public function createPasswordAction() {
        if (isset($_GET['id'], $_GET['h'], $_GET['old'], $_GET['new'])) {
            $usersModel = new Users($this->di);

            try {
                if ($usersModel->simpleCreatePassword($_GET['id'], $_GET['h'], $_GET['old'], $_GET['new'])) {
                    $this->makeJSONResponse(array(
                        'success' => 1
                    ));
                }
            } catch (Exception $e) {}
            
            $this->makeJSONResponse(array(
                'success' => 0
            ));
        }

        $this->error404();
    }

}