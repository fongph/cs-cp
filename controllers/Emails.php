<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
    CS\Devices\Limitations;

class Emails extends BaseModuleController
{

    protected $module = Modules::EMAILS;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $emailsModel = new \Models\Cp\Emails($this->di);

        if ($this->getRequest()->isAjax()) {
            $dataTableRequest = new \System\DataTableRequest($this->di);

            try {
                $data = $emailsModel->getDataTableData(
                        $this->di['devId'], $dataTableRequest->buildResult(array('account', 'path', 'timeFrom', 'timeTo'))
                );
                $this->checkDisplayLength($dataTableRequest->getDisplayLength());
                $this->makeJSONResponse($data);
            } catch (\System\DataTableRequest\ParameterNotFoundException $e) {
                $this->makeJSONResponse($dataTableRequest->buildEmptyResult());
                logException($e, ROOT_PATH . 'dataTableRequestParamNotExists.log');
            }
        }

        if ($this->view->paid) {
            $accounts = $emailsModel->getAccountsList($this->di['devId']);

            if (isset($this->params['account'])) {
                $this->view->account = $this->params['account'];
                $this->view->pathsList = array(
                    'inbox' => $this->di['t']->_('Inbox'),
                    'sent' => $this->di['t']->_('Sent'),
                    'trash' => $this->di['t']->_('Trash')
                );
            } else if (count($accounts)) {
                $this->redirect($this->di['router']->getRouteUrl('emailsSelected', array('account' => $accounts[0])));
            }

            $this->view->accountsList = $accounts;
        }

        $this->setView('cp/emails.htm');
    }

    public function viewAction()
    {
        $emailsModel = new \Models\Cp\Emails($this->di);

        if ($this->getRequest()->get('content') !== null) {
            if (($value = $emailsModel->getEmailContent($this->di['devId'], $this->params['account'], $this->params['timestamp'])) !== false) {
                echo $emailsModel->replaceImageSrc($value);
                die;
            } else {
                $this->error404();
            }
        }

        if (($emailData = $emailsModel->getEmailView($this->di['devId'], $this->params['account'], $this->params['timestamp'])) == false) {
            $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Message not found!'));
            $this->redirect($this->di['router']->getRouteUrl('emails'));
        }

        $this->view->email = $emailData;
        $this->view->account = $this->params['account'];
        $this->setView('cp/emailsView.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Emails');
    }
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::EMAILS);
    }

}
