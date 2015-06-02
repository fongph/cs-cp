<?php

namespace Models;

use System\DI,
    CS\Settings\GlobalSettings;

class Referer
{

    protected $_regexp = '/((.*)\.|^)pumpic\.com/i';
    protected $di;

    public function __construct(DI $di)
    {
        $this->di = $di;
    }

    public function setReferer()
    {
        if (!empty($_SERVER["HTTP_REFERER"])) {
            $_url = parse_url($_SERVER['HTTP_REFERER']);
            $_ref = isset($_COOKIE['orders_referer']) ? true : false;
            if( $this -> validateOrdersReferer($_ref, $_url) ) {
                setcookie("orders_referer", $_SERVER['HTTP_REFERER'], time() + 3600 * 1, '/', $this->di['config']['cookieDomain']);
            }
        }
        
        return $this;
    }
    
    public function validateOrdersReferer( $ref, $url ) {
//        if(!$ref) {
//           return false;
//        } else 
        if(!$ref and isset($url['host']) and !preg_match($this->_regexp, trim($url['host'])) ) {
           return true;
        }
        return false;
    }

    public function setDocumentReferer()
    {
        if (!empty($_SERVER["HTTP_REFERER"])) {
            $_url = parse_url($_SERVER['HTTP_REFERER']);
            if (preg_match('/^pumpic\.com(.*)/is', trim($_url['host']))) {
                if (!isset($_COOKIE['document_referer']) || $_COOKIE['document_referer'] != $_SERVER["HTTP_REFERER"]) {
                    setcookie("document_referer", $_SERVER['HTTP_REFERER'], time() + 3600 * 1, '/', $this->di['config']['cookieDomain']);
                }
            }
        }
        
        return $this;
    }

    public function setLanding()
    {
        if (!isset($_COOKIE['landing']) && isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'])) {
            $url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            setcookie("landing", $url, time() + 3600 * 1, '/', $this->di['config']['cookieDomain']);
        }
        
        return $this;
    }

    public function scroogeFrogSend()
    {
        \ScroogefrogUDPSender::sendto();
        
        return $this;
    }

}
