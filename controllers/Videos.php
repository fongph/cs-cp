<?php

namespace Controllers;

use System\FlashMessages;

class Videos extends BaseController {

    protected $module = 'videos';

    protected function init() {
        parent::init();

        $this->initCP();
    }

    //TODO: reorganize
    public function indexAction() {
        $videosModel = new \Models\Cp\Videos($this->di);

        if ($this->isPost()) {
            if (isset($_POST['network'])) {
                $devicesModel = new \Models\Devices($this->di);
                $devicesModel->setNetwork($this->di['devId'], 'videos', $_POST['network']);
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The changes have been successfully updated!'));
            }
            $this->redirect($this->di['router']->getRouteUrl('videos'));
        } else {
            if (isset($_GET['getThumb'])) {
                $url = $videosModel->getCDNAuthorizedUrl($this->di['devId'] . '/video/' . $_GET['getThumb']);
                $this->redirect($url);
            } else if (isset($_GET['getVideo'])) {
                $url = $videosModel->getAuthorizedUrl($this->di['devId'] . '/video/' . $_GET['getVideo']);
                $this->redirect($url);
            } else {
                $this->_processVideoRequests($videosModel, $this->di['router']->getRouteUrl('videos'));
            }
        }

        if ($this->view->paid) {
            $devicesModel = new \Models\Devices($this->di);
            $this->view->network = $devicesModel->getNetwork($this->di['devId'], 'videos');
            $this->view->networksList = \Models\Devices::$networksList;
            $recentVideos = $videosModel->getRecentVideos($this->di['devId']);

            if ($recentVideos > 0) {
                $this->view->recentVideos = $recentVideos;
                $this->view->cameraVideo = $videosModel->getFirstCameraVideo($this->di['devId']);
                $this->view->noCameraVideo = $videosModel->getFirstNoCameraVideo($this->di['devId']);
            }
        }

        $this->setView('cp/videos.htm');
    }

    public function cameraAction() {
        $videosModel = new \Models\Cp\Videos($this->di);

        $this->_processVideoRequests($videosModel, $this->di['router']->getRouteUrl('videosCamera'));

        $this->view->videos = $videosModel->getCameraVideos($this->di['devId']);

        $this->view->albumName = $this->di['t']->_('Camera');
        $this->setView('cp/videosAlbum.htm');
    }

    public function noCameraAction() {
        $videosModel = new \Models\Cp\Videos($this->di);

        $this->_processVideoRequests($videosModel, $this->di['router']->getRouteUrl('videosNoCamera'));

        $this->view->videos = $videosModel->getNoCameraVideos($this->di['devId']);

        $this->view->albumName = $this->di['t']->_('Other');
        $this->setView('cp/videosAlbum.htm');
    }

    protected function _processVideoRequests($videosModel, $redirectUrl) {
        if (isset($_GET['requestVideo'])) {
            try {
                if ($videosModel->setVideoRequested($this->di['devId'], $_GET['requestVideo'])) {
                    $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The video has been successfully requested!'));
                } else {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Error occurred during requesting the video!'));
                }
            } catch (\Models\Cp\VideosRecordNotFoundException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The video recording has not been found!'));
            } catch (\Models\Cp\VideosAlreadyRequestedException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The video has already been requested!'));
            } catch (\Models\Cp\VideosAlreadyUploadedException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The video has already been uploaded!'));
            } catch (\Models\Cp\VideosDeletedException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The video has been deleted and can\'t be requested!'));
            }

            $this->redirect($redirectUrl);
        } else if (isset($_GET['cancelRequest'])) {
            try {
                if ($videosModel->cancelVideoRequest($this->di['devId'], $_GET['cancelRequest'])) {
                    $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The video request has been successfully canceled!'));
                } else {
                    $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('Error occurred during cancelling the video request!'));
                }
            } catch (\Models\Cp\VideosRecordNotFoundException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The video recording has not been found!'));
            } catch (\Models\Cp\VideosAlreadyUploadedException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The video has already been uploaded!'));
            } catch (\Models\Cp\VideosDeletedException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('The video has already been deleted!'));
            } catch (\Models\Cp\VideosNoRequestToCancelException $e) {
                $this->di['flashMessages']->add(FlashMessages::ERROR, $this->di['t']->_('No video requests to cancel!'));
            }

            $this->redirect($redirectUrl);
        }
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Videos');
    }

}
