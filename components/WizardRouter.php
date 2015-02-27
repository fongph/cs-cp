<?php

namespace Components;

use System\RouteInterface;

class WizardRouter implements RouteInterface {
    
    const   STEP_PACKAGE  = 'wizardPackage',
            STEP_PLATFORM = 'wizardPlatform',
            STEP_SETUP    = 'wizardSetup',
            STEP_REGISTER = 'wizardRegister',
            STEP_FINISH   = 'wizardFinish';

    protected static $availableSteps = array(
        self::STEP_PACKAGE,
        self::STEP_PLATFORM,
        self::STEP_SETUP,
        self::STEP_REGISTER,
        self::STEP_FINISH,
    );

    protected static $currentStep;
    
    protected $platformActions, $targetStep, $uri;
    
    public $params = array();

    public function __construct($pattern, $target = self::STEP_PACKAGE, array $params = array(), array $platformActions = array())
    {
        $this->uri = $pattern;

        if(in_array($target, self::$availableSteps)){
            $this->targetStep = $target;

        } else throw new InvalidWizardRouterStep;

        $this->params = $params;
        
        $this->platformActions = $platformActions;
    }

    public function isMatch($uri)
    {
        if($this->uri == $uri){
            self::$currentStep = $this->targetStep;
            return true;

        } else return false;
    }
    
    public function isAvailableStep($name)
    {
        switch($name){
            case self::STEP_PACKAGE: return true;

            case self::STEP_PLATFORM: return self::$currentStep != self::STEP_FINISH && (isset($this->params['licenseId']));
            
            case self::STEP_SETUP: return self::$currentStep != self::STEP_FINISH && (isset($this->params['licenseId'], $this->params['platform']));
            
            case self::STEP_REGISTER: return self::$currentStep != self::STEP_FINISH && (isset($this->params['licenseId'], $this->params['platform']));
            
            case self::STEP_FINISH: return self::$currentStep == self::STEP_FINISH;
            
            default: return false;
        }
    }
    
    public function isCurrentStep($name)
    {
        return self::$currentStep === $name;
    }

    public function getUri(array $params = array())
    {
        if(self::$currentStep == self::STEP_FINISH && $this->targetStep != self::STEP_FINISH || empty($this->params) && empty($params)) {
            return $this->uri;
            
        } else return "{$this->uri}?{$this->getParamString($params)}";
    }

    public function getParamString(array $params = array())
    {
        $parts = array();
        foreach(array_merge($this->params, $params) as $p => $v) if($v)
            $parts[] = "{$p}={$v}";
        return implode('&', $parts);
    }
    
    public function __get($name)
    {
        if($name == 'target'){
           
            foreach($this->platformActions as $platformPattern => $actionName){
                if(preg_match("#^{$platformPattern}$#", @$this->params['platform']))
                    return array('controller'=>'Wizard','action'=>$actionName);
            }
            
            return array('controller'=>'Wizard','action'=>'redirect');

        } else return $this->{null};
    }
    
    public function __isset($name)
    {
        return $name === 'target';
    }

}

class InvalidWizardRouterStep extends \Exception {}