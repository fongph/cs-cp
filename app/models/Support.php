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

    const OPTION_SUPPORT_ADDITIONL_MESSAGE = 'cp-support-additional-message';

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

        if (!isset(self::$types[$type])) {
            throw new SupportInvalidTypeException("Invalid type of ticket");
        }

        return $this->sendTicket($name, $email, self::$types[$type], $message);
    }

    public function sendTicket($name, $email, $type, $message)
    {
        $number = $this->getCurrentTicketNumber();
        $info = $this->getUserAgentInfoAll();

        $this->di['mailSender']->sendSystemSupportTicket($this->di['config']['supportEmail'], $number, $name, $email, $type, $message, $info['browser'], $info['platform'], $info);
        $this->di['mailSender']->sendSupportTicketUser($this->di['config']['supportEmail'], $number, $name, $email, $type, $message, $info['browser'], $info['platform']);

        return $number;
    }

    public function getUserAgentInfoAll()
    {
        $info = get_browser();
        $result = array();
        if (isset($info->browser, $info->version)) {
            $result['browser'] = $info->browser;
            $result['browser_version'] = $info->version;
        }

        if (isset($info->platform)) {
            $result['platform'] = $info->platform;
            if (isset($info->platform_version)) {
                $result['platform_version'] = $info->platform_version;
            }
        }

        if (isset($info->ismobiledevice))
            $result['ismobiledevice'] = $info->ismobiledevice;

        if (isset($info->istablet))
            $result['istablet'] = $info->istablet;

        if (isset($_COOKIE['_screen']))
            $result['_screen'] = $_COOKIE['_screen'];

        return $result;
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

    public function getAdditionalMessage()
    {
        $name = $this->getDb()->quote(self::OPTION_SUPPORT_ADDITIONL_MESSAGE);
        return $this->getDb()->query("SELECT `value` FROM `options` WHERE `name` = {$name}")->fetchColumn();
    }

}
