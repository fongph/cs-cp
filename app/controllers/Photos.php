<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
    CS\Models\Device\DeviceModulesRecord,    
    CS\Devices\Limitations;

class Photos extends BaseModuleController
{

    protected $module = Modules::PHOTOS;
    protected $lengthPage = 10;
    
    protected function init()
    {
        parent::init();

        $this->initCP();
    }

    public function indexAction()
    {
        $photosModel = new \Models\Cp\Photos($this->di);

        if ($this->getRequest()->hasPost('network')) {
            $this->checkDemo($this->di['router']->getRouteUrl('photos'));
            
            $settingsModel = new \Models\Cp\Settings($this->di);
            $settingsModel->setNetwork($this->di['devId'], 'photos', $this->getRequest()->post('network'));
            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The changes have been successfully updated!'));
            $this->redirect($this->di['router']->getRouteUrl('photos'));
        }
        if ($this->view->paid) {
            $settingsModel = new \Models\Cp\Settings($this->di);
            $this->view->network = $settingsModel->getNetwork($this->di['devId'], 'photos');
            $this->view->networksList = \Models\Cp\Settings::$networksList;

            $this->view->recentPhotos = $photosModel->getRecentPhotos($this->di['devId']);
            $this->view->albums = $photosModel->getAlbums($this->di['devId']);
            $this->view->hasRecords = (count($this->view->albums) || count($this->view->recentPhotos));
        }

        $this->setView('cp/photos.htm');
    }

    public function albumAction()
    {
        $photosModel = new \Models\Cp\Photos($this->di);
        
        if ($this->getRequest()->isAjax()) {
            $currPage = ($this->getRequest()->hasPost('currPage') && $this->getRequest()->post('currPage') > 0) ? $this->getRequest()->post('currPage') : 0;
            $perPage = ($this->getRequest()->hasPost('perPage') && $this->getRequest()->post('perPage') > 0) ? $this->getRequest()->post('perPage') : $this->lengthPage;
            
            $data = $photosModel->getAlbumPhotos($this->di['devId'], $this->params['album'], $currPage, $perPage);
            $this->makeJSONResponse($data);
        }
        
        $this->view->hasPhotos = $photosModel->getCountItems($this->di['devId'], $this->params['album']);
//        $this->view->lengthPage = $this->lengthPage;
//        $this->view->totalPage = $photosModel->getTotalPages($this->di['devId'], $this->params['album'], $this->lengthPage);
        $this->view->albumName = $this->params['album'];

        $this->setView('cp/photosAlbum.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('Photos');

        if($this->di['currentDevice']['os'] != 'icloud'){
            $this->view->customTimezoneOffset = 0;
        }
        
        $this->view->moduleId = Modules::PHOTOS;
    }
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::PHOTOS);
    }

}
