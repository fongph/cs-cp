<?php

namespace Controllers;

use System\Controller;

class BaseController extends Controller
{

    protected $auth = null;

    protected function init()
    {
        if ($this->di['auth']->hasIdentity()) {
            $this->auth = $this->di['auth']->getIdentity();
        }
    }

    public function error404()
    {
        header($this->getRequest()->server('SERVER_PROTOCOL', 'HTTP/1.1') . ' 404 Not Found', true, 404);
        $this->view->title = $this->di['t']->_('Not Found');
        $this->setView('index/404.htm');
        $this->response();
        die;
    }

    protected function postAction()
    {
        if ($this->auth) {
            $this->view->authData = $this->auth;
        }
        
        if (isset($this->auth['options']['internal-trial-license'])) {
            $advertisingModel = new \Models\Advertising($this->di);
            $this->view->showInternalTrialLicenseProlongBanner = $advertisingModel->showInternalTrialLicenseProlongBanner($this->auth['options']['internal-trial-license']);
        }
    }

    protected function checkDisplayLength($value = 10)
    {
        if ($value !== $this->auth['records_per_page']) {
            $usersModel = new \Models\Users($this->di);
            $usersModel->setRecordsPerPage($value);
            $usersModel->reLogin();
        }
    }

}
