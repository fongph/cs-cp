<?php

namespace Controllers;

class CP extends BaseController {

    protected function init() {
        parent::init();
    }

    public function mainAction() {
        $this->redirectPermanently($this->di['router']->getRouteUrl('calls'));
    }

    public function setDeviceAction() {
        $devicesModel = new \Models\Devices($this->di);
        $devicesModel->setCurrentDevId($this->params['devId']);
        $this->redirectToLastUsedModule();
    }

    protected function postAction() {
        parent::postAction();
        $this->buildCpMenu();
    }

    protected function redirectToLastUsedModule() {
        
        // redirect
        if($this->getRequest()->get('redirect_url')) {
            $redirect = explode('/', $this->getRequest()->get('redirect_url'));
            foreach ($this->di['config']['modules'] as $key => $value) {
                if(in_array($key, $redirect)) {
                    $this->redirect( $this->di['router']->getRouteUrl( array_pop( $redirect ) ) );
                }
            }          
        }
        
        if ($this->getRequest()->hasServer('HTTP_REFERER')) {
            $prevRequestUri = parse_url($this->getRequest()->server('HTTP_REFERER'), PHP_URL_PATH);

            $this->di['router']->execute($prevRequestUri, function($route, $routeName) {
                foreach ($this->di['config']['modules'] as $key => $value) {
                    if (($routeName == $key) || substr($routeName, 0, strlen($key)) == $key) {
                        $this->redirect($this->di['router']->getRouteUrl($key));
                    }
                }
            }, false);
        }

        $this->redirect($this->di['router']->getRouteUrl('calls'));
    }

}
