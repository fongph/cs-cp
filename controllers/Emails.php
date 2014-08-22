<?php

namespace Controllers;

use System\FlashMessages;

class Emails extends BaseController {

    protected $module = 'emails';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    //TODO: make with DataTableRequest and build own page for every email
    public function indexAction() {
        $emailsModel = new \Models\Cp\Emails($this->di);

        if ($this->isAjaxRequest()) {
            $dataTableRequest = new \System\DataTableRequest();

            try {
                $dataTableRequest->getRequest($_GET, array('account', 'path', 'timeFrom', 'timeTo'));
                $this->checkDisplayLength($dataTableRequest->getDisplayLength());
                $this->makeJSONResponse($emailsModel->getDataTableData($this->di['devId'], $dataTableRequest->getResult()));
            } catch (\System\DataTableRequestParamNotExists $e) {
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

    public function viewAction() {
        $emailsModel = new \Models\Cp\Emails($this->di);

        if (isset($_GET['content'])) {
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

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Emails');
    }

}
