<?php

namespace Controllers;

use System\FlashMessages,
    Models\Modules,
    CS\Devices\Limitations;

class Videos extends BaseModuleController
{

    protected $module = Modules::VIDEOS;

    protected function init()
    {
        parent::init();
        $this->initCP();
    }

    //TODO: reorganize
    public function indexAction()
    {
        $videosModel = new \Models\Cp\Videos($this->di);

        if ($this->getRequest()->isPost()) {
            if ($this->getRequest()->hasPost('network')) {
                $this->checkDemo($this->di['router']->getRouteUrl('videos'));
                
                $settingsModel = new \Models\Cp\Settings($this->di);
                $settingsModel->setNetwork($this->di['devId'], 'videos', $this->getRequest()->post('network'));
                $this->di['flashMessages']->add(FlashMessages::SUCCESS, $this->di['t']->_('The changes have been successfully updated!'));
                $this->redirect($this->di['router']->getRouteUrl('videos'));
            }
        } else {
            if ($this->getRequest()->hasGet('getThumb')) {
                $url = $videosModel->getCDNAuthorizedUrl($this->di['devId'] . '/video/' . $this->getRequest()->get('getThumb'));
                $this->redirect($url);
            } else if ($this->getRequest()->hasGet('getVideo')) {
                $url = $videosModel->getCDNAuthorizedUrl($this->di['devId'] . '/video/' . $this->getRequest()->get('getVideo'));
                $this->redirect($url);
            } else {
                $this->processVideoRequests($videosModel, $this->di['router']->getRouteUrl('videos'));
            }
        }

        if ($this->view->paid) {
            $this->view->supportMode = $this->supportMode;
            
            $settingsModel = new \Models\Cp\Settings($this->di);
            $this->view->network = $settingsModel->getNetwork($this->di['devId'], 'videos');
            $this->view->networksList = \Models\Cp\Settings::$networksList;
            $recentVideos = $videosModel->getRecentVideos($this->di['devId']);

            if ($recentVideos > 0) {
                $this->view->recentVideos = $recentVideos;
                $this->view->cameraVideo = $videosModel->getFirstCameraVideo($this->di['devId']);
                $this->view->noCameraVideo = $videosModel->getFirstNoCameraVideo($this->di['devId']);
            }
        }

        $this->setView('cp/videos.htm');
    }

    public function cameraAction()
    {
        $videosModel = new \Models\Cp\Videos($this->di);

        $this->processVideoRequests($videosModel, $this->di['router']->getRouteUrl('videosCamera'));

        $this->view->videos = $videosModel->getCameraVideos($this->di['devId']);
        $this->view->supportMode = $this->supportMode;
        $this->view->albumName = $this->di['t']->_('Camera');
        $this->setView('cp/videosAlbum.htm');
    }

    public function noCameraAction()
    {
        $videosModel = new \Models\Cp\Videos($this->di);

        $this->processVideoRequests($videosModel, $this->di['router']->getRouteUrl('videosNoCamera'));

        $this->view->videos = $videosModel->getNoCameraVideos($this->di['devId']);
        $this->view->supportMode = $this->supportMode;
        $this->view->albumName = $this->di['t']->_('Other');
        $this->setView('cp/videosAlbum.htm');
    }

    protected function processVideoRequests($videosModel, $redirectUrl)
    {
        if ($this->getRequest()->hasGet('requestVideo')) {
            $this->checkDemo($redirectUrl);
            
            try {
                if ($videosModel->setVideoRequested($this->di['devId'], $this->getRequest()->get('requestVideo'))) {
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
        } else if ($this->getRequest()->hasGet('cancelRequest')) {
            $this->checkDemo($redirectUrl);
            
            try {
                if ($videosModel->cancelVideoRequest($this->di['devId'], $this->getRequest()->get('cancelRequest'))) {
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

    protected function postAction()
    {
        parent::postAction();
        $this->buildCpMenu();

        $this->view->title = $this->di['t']->_('View Videos');
    }
    
    protected function isModulePaid()
    {
        $devicesLimitations = new Limitations($this->di['db']);
        return $devicesLimitations->isAllowed($this->di['devId'], Limitations::VIDEO);
    }

}
