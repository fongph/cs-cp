<?php

namespace Controllers;

use Models\Modules,
    CS\Devices\Limitations;

class Calendar extends BaseModuleController
{

    protected $module = Modules::CALENDAR;

    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    //TODO: reorganize
    public function indexAction()
    {
        $calendarModel = new \Models\Cp\Calendar($this->di);
        if ($this->getRequest()->isAjax()) {
            $timeFrom = floor($this->getRequest()->get('from', 0) / 1000);
            $timeTo = ceil($this->getRequest()->get('to', 0) / 1000);

            if ($timeTo - $timeFrom > 60 * 60 * 24 * 32) {
                $this->error404();
            }

            $this->makeJSONResponse($calendarModel->getEventsList($this->di['devId'], $timeFrom, $timeTo, $this->getRequest()->get('offset', 0)));
        }

        $this->view->hasRecords = $calendarModel->hasRecords($this->di['devId']);

        $this->setView('cp/calendar.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Calendar');
    }
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::CALENDAR);
    }

}
