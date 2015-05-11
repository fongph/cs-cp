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
        if (!empty($_SERVER["HTTP_REFERER"]) ) {
            $_url = parse_url($_SERVER['HTTP_REFERER']); 
            if(!preg_match($this -> _regexp, trim($_url['host'])) || !isset($_COOKIE['orders_referer']) ) {
                setcookie("orders_referer", $_SERVER['HTTP_REFERER'], time() + 3600 * 1, '/', $this->di['config']['cookieDomain'] );
            }
        }    
    }
    
    public function setDocumentReferer() 
    {
        if (!empty($_SERVER["HTTP_REFERER"]) ) {
            $_url = parse_url($_SERVER['HTTP_REFERER']); 
            if(preg_match('/^pumpic\.com(.*)/is', trim($_url['host']))) {
                if(!isset($_COOKIE['document_referer']) || $_COOKIE['document_referer'] != $_SERVER["HTTP_REFERER"]) {
                    setcookie("document_referer", $_SERVER['HTTP_REFERER'], time() + 3600 * 1, '/', $this->di['config']['cookieDomain'] );
                } 
            }
        }
    }
    
    public function scroogeFrogSend(){
        \ScroogefrogUDPSender::sendto();
    }
    
}
