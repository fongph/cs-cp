<?php

namespace Models;

use Models\Support\SupportEmptyFieldException,
    Models\Support\SupportInvalidEmailException,
    Models\Support\SupportInvalidTypeException;

class Support extends \System\Model
{

    static $types = array(
        0 => 'General question',
        1 => 'Technical question',
        2 => 'Billing question',
        3 => 'Website feedback'
    );

    public function getTypesList()
    {
        $result = self::$types;
        foreach ($result as $key => $value) {
            $result[$key] = $this->di->get('t')->_($value);
        }

        return $result;
    }

    public function submitTicket($name, $email, $type, $message)
    {
        if (!(strlen($name) && strlen($email) && strlen($type) && strlen($message))) {
            throw new SupportEmptyFieldException("One or more fields is empty");
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new SupportInvalidEmailException("Invalid email value");
        }

        if (!isset(self::$types[$type])) {
            throw new SupportInvalidTypeException("Invalid type of ticket");
        }

        return $this->sendTicket($name, $email, self::$types[$type], $message);
    }

    public function sendTicket($name, $email, $type, $message)
    {
        $number = $this->getCurrentTicketNumber();
        $info = $this->getUserAgentInfo();

        $this->di['mailSender']->sendSupportTicket($this->di['config']['supportEmail'], $number, $name, $email, $type, $message, $info['browser'], $info['os']);

        return $number;
    }

    public function getUserAgentInfo()
    {
        $info = get_browser();

        $result = array(
            'os' => $this->di['t']->_('Not defined'),
            'browser' => $this->di['t']->_('Not defined')
        );

        if (isset($info->platform)) {
            $result['os'] = $info->platform;
            if (isset($info->platform_version)) {
                $result['os'] .= ' ' . $info->platform_version;
            }
        }

        if (isset($info->browser)) {
            $result['browser'] = $info->browser;
            if (isset($info->version)) {
                $result['browser'] .= ' ' . $info->version;
            }
        }

        return $result;
    }

    public function getCurrentTicketNumber()
    {
        $this->getDb()->exec("UPDATE `options` SET `value` = `value` + 1 WHERE `name` = 'ticketNumber'");
        return $this->getDb()->query("SELECT `value` FROM `options` WHERE `name` = 'ticketNumber'")->fetchColumn();
    }

}
