<?php

namespace Controllers;

use System\FlashMessages;

class Photos extends BaseController {

    protected $module = 'photos';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    public function indexAction() {
        $photosModel = new \Models\Cp\Photos($this->di);

        $devicesModel = new \Models\Devices($this->di);
        if (isset($_POST['network'])) {
            $devicesModel->setNetwork($this->di['devId'], 'photos', $_POST['network']);
            $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The changes have been successfully updated!'));
            $this->redirect($this->di['router']->getRouteUrl('photos'));
        }

        if ($this->view->paid) {
            $this->view->network = $devicesModel->getNetwork($this->di['devId'], 'photos');
            $this->view->networksList = \Models\Devices::$networksList;

            $this->view->recentPhotos = $photosModel->getRecentPhotos($this->di['devId']);
            $this->view->albums = $photosModel->getAlbums($this->di['devId']);
        }

        $this->setView('cp/photos.htm');
    }

    public function albumAction() {
        $photosModel = new \Models\Cp\Photos($this->di);
        $this->view->photos = $photosModel->getAlbumPhotos($this->di['devId'], $this->params['album']);

        $this->view->albumName = $this->params['album'];

        $this->setView('cp/photosAlbum.htm');
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Photos');
    }

}
