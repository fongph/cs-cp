<?php

namespace Controllers;

use System\Controller,
    System\FlashMessages;

class BaseController extends Controller
{

    protected $auth = null;
    protected $demo = false;
    protected $supportMode = false;

    protected function init()
    {
        $refere = new \Models\Referer($this->di);
        $refere->setReferer()
                ->setLanding();

        if ($this->di['auth']->hasIdentity()) {
            $this->auth = $this->di['auth']->getIdentity();
            
            if (isset($this->auth['support_mode'])) {
                $this->supportMode = true;
            }

            if (!$this->di['config']['demo'] && !$this->getRequest()->hasCookie('s') && !isset($this->auth['admin_id'])) {
                $this->di['usersManager']->logAuth($this->auth['id']);

                $usersModel = new \Models\Users($this->di);
                $usersModel->setAuthCookie();
            }
            if(!isset($this->auth['support_mode'])){
                $users = new \Models\Users($this->di);

                $acceptance = array('policy', 'tos');
                foreach ($acceptance as $item) {
                    $userAcceptance = $users->checkUserLegalAcceptance($this->auth['id'], $item);
                    $currentPath = str_replace('/', '', $this->getRequest()->uri());
                    if ($currentPath == $item) break;
                    elseif ($currentPath == 'logout') continue;
                    elseif ($userAcceptance === '0') $this->redirect($this->di->getRouter()->getRouteUrl($item));
                }
            }
        }

        if ($this->di['config']['demo']) {
            $this->demo = true;
            $refere->setDocumentReferer();
                    // ->scroogeFrogSend();
        }

        if ($this->di->getRequest()->hasGet('setDeviceId')) {
            $this->setDevice();
        }
    }

    public function error404()
    {
        header($this->getRequest()->server('SERVER_PROTOCOL', 'HTTP/1.1') . ' 404 Not Found', true, 404);
        $this->view->title = $this->di['t']->_('Not Found');
        $this->setView('index/404.htm');
        $this->postAction();
        $this->response();
        die;
    }

    protected function postAction()
    {
        if ($this->auth) {
            $this->view->authData = $this->auth;
            $this->view->isDirectLogin = isset($this->auth['admin_id']) && (int) $this->auth['admin_id'];
        } else {
            $this->view->isDirectLogin = false;
        }

        $this->view->supportMode = $this->supportMode;
        $this->view->demoMode = $this->demo;
        
        if (!isset($this->view->norobots)) {
            $this->view->norobots = true;
        }

        if (isset($this->auth['options']['internal-trial-license'])) {
            $advertisingModel = new \Models\Advertising($this->di);

            $this->view->internalTrialLicenseDaysLeft = $advertisingModel->getInternalTrialLicenseDaysLeft($this->auth['id'], $this->auth['options']['internal-trial-license']);
        }
    }

    protected function checkDisplayLength($value = 10)
    {
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

    protected function checkDemo($redirectUrl, $addFlashMessage = true)
    {
        if ($this->demo) {
            if ($addFlashMessage) {
                $this->di->getFlashMessages()->add(FlashMessages::INFO, $this->di['t']->_('Not available in demo.'));
            }

            $this->redirect($redirectUrl);
        }
    }

    protected function checkSupportMode($addFlashMessage = true)
    {
        if ($this->supportMode) {
            if ($addFlashMessage) {
                $this->di->getFlashMessages()->add(FlashMessages::INFO, $this->di['t']->_('Not available in support mode.'));
            }

            $this->redirect($this->di->getRouter()->getRouteUrl('calls'));
        }
    }

    private function setDevice()
    {
        $deviceId = $this->di->getRequest()->get('setDeviceId');

        if ($deviceId > 0) {
            $devicesModel = new \Models\Devices($this->di);
            $devicesModel->setCurrentDevId($deviceId);
        }

        $server = $this->di->getRequest()->server();

        $url = \League\Url\Url::createFromServer($server);
        $query = $url->getQuery();
        $query->offsetUnset('setDeviceId');

        $this->redirect((string) $url);
    }
    public function redirectPermanently($url, $statusCode = 301)
    {
        header('Location: ' . $url, true, $statusCode);
        die;
    }

}
