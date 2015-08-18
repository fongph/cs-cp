<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
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
        }

        $this->setView('cp/photos.htm');
    }

    public function albumAction()
    {
        $photosModel = new \Models\Cp\Photos($this->di);
        
        if ($this->getRequest()->isAjax()) {
            $currPage = ($this->getRequest()->hasPost('currPage') && $this->getRequest()->post('currPage') > 0) ? $this->getRequest()->post('currPage') - 1 : 0;
            $data = $photosModel->getAlbumPhotos($this->di['devId'], $this->params['album'], $currPage, $this->lengthPage);
            $this->makeJSONResponse($data);
        }
        
        $this->view->photos = $photosModel->getAlbumPhotos($this->di['devId'], $this->params['album'], 0, $this->lengthPage);
        $this->view->lengthPage = $this->lengthPage;
        $this->view->totalPage = $photosModel->getTotalPages($this->di['devId'], $this->params['album'], $this->lengthPage);
        $this->view->albumName = $this->params['album'];

        $this->setView('cp/photosAlbum.htm');
    }

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Photos');

        if($this->di['currentDevice']['os'] != 'icloud'){
            $this->view->customTimezoneOffset = 0;
        }
    }
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::PHOTOS);
    }

}
