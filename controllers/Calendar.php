<?php

namespace Controllers;

class Calendar extends BaseController {

    protected $module = 'calendar';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    //TODO: reorganize
    public function indexAction() {
        $calendarModel = new \Models\Cp\Calendar($this->di);
        if ($this->isAjaxRequest()) {
            $from = floor($_GET['from'] / 1000);
            $to = ceil($_GET['to'] / 1000);

            if ($to - $from > 60 * 60 * 24 * 32) {
                $this->error404();
            }

            $this->makeJSONResponse($calendarModel->getEventsList($this->di['devId'], $from, $to, $_GET['offset']));
        }

        if ($this->view->paid) {
            $this->view->hasRecords = $calendarModel->hasRecords($this->di['devId']);
        }

        $this->setView('cp/calendar.htm');
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Calendar');
    }

}
