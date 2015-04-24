<?php

namespace Controllers;

use System\Controller,
    System\FlashMessages;

class BaseController extends Controller {

    protected $auth = null;
    protected $demo = false;
    protected $supportMode = false;

    protected function init() {
        if ($this->di['auth']->hasIdentity()) {
            $this->auth = $this->di['auth']->getIdentity();

            if (isset($this->auth['support_mode'])) {
                $this->supportMode = true;
            }

            if (!$this->di['config']['demo'] && !$this->getRequest()->hasCookie('s') && !isset($this->auth['admin_id'])) {
                $this->di['logger']->addInfo("Logging not admin authentiacation", array('cookie' => $_COOKIE, 'auth' => $this->auth));

                $this->di['usersManager']->logAuth($this->auth['id']);

                $usersModel = new \Models\Users($this->di);
                $usersModel->setAuthCookie();
            }
        }

        if ($this->di['config']['demo']) {
            $this->demo = true;
            $refereDemo = new \Models\Referer($this->di);
            $refereDemo->setReferer();
            $refereDemo->setDocumentReferer();
            $refereDemo->scroogeFrogSend();
        }
    }

    public function error404() {
        header($this->getRequest()->server('SERVER_PROTOCOL', 'HTTP/1.1') . ' 404 Not Found', true, 404);
        $this->view->title = $this->di['t']->_('Not Found');
        $this->setView('index/404.htm');
        $this->response();
        die;
    }

    protected function postAction() {
        if ($this->auth) {
            $this->view->authData = $this->auth;
            $this->view->supportMode = $this->supportMode;
            $this->view->demoMode = $this->demo;
        }

        if (isset($this->auth['options']['internal-trial-license'])) {
            $advertisingModel = new \Models\Advertising($this->di);

            $this->view->internalTrialLicenseDaysLeft = $advertisingModel->getInternalTrialLicenseDaysLeft($this->auth['id'], $this->auth['options']['internal-trial-license']);
        }
    }

    protected function checkDisplayLength($value = 10) {
        if ($value !== $this->auth['records_per_page']) {
            if ($this->demo) {
                $data = $this->di['auth']->getIdentity();
                $data['records_per_page'] = $value;
                $this->di['auth']->setIdentity($data);
            } else {
                $usersModel = new \Models\Users($this->di);
                $usersModel->setRecordsPerPage($value);
                $usersModel->reLogin();
            }
        }
    }

    protected function checkDemo($redirectUrl, $addFlashMessage = true) {
        if ($this->demo) {
            if ($addFlashMessage) {
                $this->di->getFlashMessages()->add(FlashMessages::INFO, $this->di['t']->_('Not available in demo.'));
            }

            $this->redirect($redirectUrl);
        }
    }

    protected function checkSupportMode($addFlashMessage = true) {
        if ($this->supportMode) {
            if ($addFlashMessage) {
                $this->di->getFlashMessages()->add(FlashMessages::INFO, $this->di['t']->_('Not available in support mode.'));
            }

            $this->redirect($this->di->getRouter()->getRouteUrl('cp'));
        }
    }

}
