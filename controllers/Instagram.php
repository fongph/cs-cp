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
        
        if ($this->getRequest()->isAjax() && $this->getRequest()->hasPost('account', 'dateFrom', 'dateTo')) {
            $accountId = $this->getRequest()->post('account');
            $dateFrom = $this->getRequest()->post('dateFrom');
            $dateTo = $this->getRequest()->post('dateTo');
            $page = $this->getRequest()->post('page', 1);
            return $this->makeJSONResponse($instagramModel->getPostedData($this->di['devId'], $accountId, $dateFrom, $dateTo, 3, $page));
        }
        
        $this->view->accounts = $instagramModel->getAccounts($this->di['devId']);
        
        //p($instagramModel->getPostedData($this->di['devId'], '1649797507', 0, 99999999999999, $this->auth['records_per_page']));
        
        $this->setView('cp/instagram.htm');
    }

    public function viewAction()
    {
        $instagramModel = new \Models\Cp\Instagram($this->di);
        $this->view->post = $instagramModel->getPost($this->di['devId'], $this->params['account'], $this->params['post']);
        
        p($this->view->post, 1);

        $this->setView('cp/instagramPost.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Instagram Tracking');
    }

    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::INSTAGRAM);
    }

}
