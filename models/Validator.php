<?php

namespace Models;

class Validator extends \System\Validator {

    private static $_defaultMessageKeyName = 'defaultMessage';
    private $_errorMessages = array();
    public $messages = array();

    public function __construct() {
        $registry = \System\Registry::getInstance();
        $this->_errorMessages = $registry->messages['validator'];
    }

    public function validateField($value, $validators) {
        $messages = array();

        foreach ($validators as $validator) {
            switch ($validator['type']) {
                case 'required':
                    if (!self::validateRequired($value)) {
                        $messages[] = $this->_getMessage($validator['type']);
                    }
                    break;
                case 'equalto':
                    if (!self::validateEqualTo($value, $validator['equalValue'])) {
                        $messages[] = $this->_getMessage($validator['type']);
                    }
                    break;
                case 'email':
                    if (!self::validateEmail($value)) {
                        $messages[] = $this->_getMessage($validator['type']);
                    }
                    break;
                case 'callback':
                    $data = $validator['callback']($value);
                    if (strlen($data)) {
                        $messages[] = $data;
                    }
                    break;
            }
        }

        return $messages;
    }

    private function _getMessage($type) {
        if (isset($this->_errorMessages[$type])) {
            return $this->_errorMessages[$type];
        } elseif (isset($this->_errorMessages[self::$_defaultMessageKeyName])) {
            return $this->_errorMessages[self::$_defaultMessageKeyName];
        } else {
            return '';
        }
    }

}