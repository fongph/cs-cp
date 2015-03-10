<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
    CS\Devices\Limitations;

class Instagram extends BaseModuleController
{

    protected $module = Modules::INSTAGRAM;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $instagramModel = new \Models\Cp\Instagram($this->di);

        
        if (($account = $instagramModel->getFirstAccount($this->di['devId'])) !== false) {
            
            $this->redirect($this->getDI()->getRouter()->getRouteUrl('instagramTab', array('account' => $account, 'tab' => 'own')));
        }

        $this->setView('cp/instagram.htm');
    }

    public function tabAction()
    {
        $instagramModel = new \Models\Cp\Instagram($this->di);

        if ($this->getRequest()->isAjax() && $this->getRequest()->hasPost('dateFrom', 'dateTo')) {
            $dateFrom = $this->getRequest()->post('dateFrom');
            $dateTo = $this->getRequest()->post('dateTo');
            $page = $this->getRequest()->post('page', 1);

            switch ($this->params['tab']) {
                case 'own':
                    return $this->makeJSONResponse($instagramModel->getOwnPostsData($this->di['devId'], $this->params['account'], $dateFrom, $dateTo, $this->auth['records_per_page'], $page));
                case 'friends':
                    return $this->makeJSONResponse($instagramModel->getFriendsPostsData($this->di['devId'], $this->params['account'], $dateFrom, $dateTo, $this->auth['records_per_page'], $page));
                case 'commented':
                    return $this->makeJSONResponse($instagramModel->getCommentedPostsData($this->di['devId'], $this->params['account'], $dateFrom, $dateTo, $this->auth['records_per_page'], $page));
            }
        }

        $this->view->accounts = $instagramModel->getAccounts($this->di['devId']);

        if (!count($this->view->accounts)) {
            $this->redirect($this->getDI()->getRouter()->getRouteUrl('instagram'));
        }

        $this->view->selectedAccount = $this->params['account'];
        $this->view->tab = $this->params['tab'];

        $this->setView('cp/instagram.htm');
    }

    public function viewAction()
    {
        $instagramModel = new \Models\Cp\Instagram($this->di);
        $post = $instagramModel->getPost($this->di['devId'], $this->params['account'], $this->params['post']);

        if ($post === false) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The post was not found.'));
            $this->redirect($this->di['router']->getRouteUrl('instagram'));
        }

        if ($this->getRequest()->hasGet('requestVideo')) {
            $redirectUrl = $this->di['router']->getRouteUrl('instagramPost', array(
                        'account' => $this->params['account'],
                        'post' => $this->params['post']
            ));
            
            $this->checkDemo($redirectUrl);
            
            if ($post['type'] == 'photo' || $post['status'] != 'image-saved') {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Invalid request!'));
            } else {
                $instagramModel->setPostVideoRequestedStatus($post['id']);
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The request to download the video was successfully sent.'));
            }

            $this->redirect($redirectUrl);
        }

        $this->view->comments = $instagramModel->getPostComments($this->di['devId'], $this->params['account'], $this->params['post']);
        $this->view->post = $post;
        $this->view->account = $this->params['account'];

        $this->setView('cp/instagramPost.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();
        
        if ($this->di['isTestUser']($this->auth['id'])) {
            if (($this->di['currentDevice']['os'] === 'android' && $this->di['currentDevice']['app_version'] < 5) ||
                    ($this->di['currentDevice']['os'] === 'ios' && $this->di['currentDevice']['app_version'] < 3)) {
                $this->view->showUpdateBlock = $this->di['currentDevice']['os'];
            }
        }

        $this->view->title = $this->di['t']->_('Instagram Tracking');
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::INSTAGRAM);
    }

}
