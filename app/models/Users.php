<?php

namespace Models;

use IP,
    System\Model,
    System\Session,
    CS\Users\UsersManager,
    CS\Models\User\UserRecord,
    CS\Settings\GlobalSettings;

class Users extends Model {

    protected $recordsPerPageList = array(10, 25, 50, 100);

    public function __construct($di)
    {
        parent::__construct($di);
    }

    public function getUserRecord()
    {
        return new UserRecord($this->getDb());
    }

    /**
     * 
     * @return UsersManager
     */
    public function getUsersManager()
    {
        return $this->di['usersManager'];
    }

    public function login($email, $password, $remember = false)
    {
        $usersManager = new UsersManager($this->getDb());
        $usersManager->setSender($this->di['mailSender']);
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $ip = IP::getRealIP();
        $environment = array('from' => 'ControlPanel',
            'userAgent' => $userAgent,
            'ip' => $ip);

        $data = $usersManager->login($this->di['config']['site'], $email, $password, '', $environment);

        $this->di['auth']->setIdentity($data);

        if ($remember) {
            Session::rememberMe();
        } else {
            Session::regenerateId();
        }

        $this->setLocale($data['locale'], false);
        return true;
    }

    public function setSettings($data)
    {   
        $auth = $this->di['auth']->getIdentity();

        $userRecord = $this->getUserRecord()
                ->load($auth['id']);

        if (isset($data['locale'])) {
            if (array_key_exists($this->di->getRequest()->post('locale'), $this->di['config']['locales'])) {
                $userRecord->setLocale($data['locale']);
            }
        }

        if (isset($data['recordsPerPage'])) {
            if (!in_array($data['recordsPerPage'], $this->recordsPerPageList)) {
                $data['recordsPerPage'] = $this->recordsPerPageList[0];
            }

            $userRecord->setRecordsPerPage($data['recordsPerPage']);
        }

        $userRecord->save();

        $this->setSubscribes($auth['id'], $data['subscribes']);
        
        $this->reLogin();

        return true;
    }

    public function setRecordsPerPage($value)
    {
        $auth = $this->di['auth']->getIdentity();

        $userRecord = $this->getUserRecord()
                ->load($auth['id']);

        $userRecord->setRecordsPerPage($value);

        return $userRecord->save();
    }

    public function directLogin($userId, $adminId, $hash, $supportMode = false)
    {
        $usersManager = $this->getUsersManager();

        try {
            $data = $usersManager->getDirectLoginUserData(
                    $this->di['config']['site'], $userId, $adminId, $supportMode, $hash, GlobalSettings::getDirectLoginSalt($this->di['config']['site'])
            );
        } catch (\CS\Users\DirectLoginException $e) {
            $this->di['logger']->addAlert("Direct Login Error", array('exception' => $e));
            return false;
        }

        $this->di['auth']->setIdentity($data);
        $this->setLocale($data['locale'], false);

        return true;
    }

    public function loginById($id, $mergeData = array())
    {
        $usersManager = $this->getUsersManager();

        $data = $usersManager->getUserDataById($this->di['config']['site'], $id);

        $this->di['auth']->setIdentity(array_merge($data, $mergeData));

        $this->setLocale($data['locale'], false);

        return true;
    }

    public function reLogin()
    {
        $data = $this->di['auth']->getIdentity();

        $mergeData = array();

        if (isset($data['admin_id'])) {
            $mergeData['admin_id'] = $data['admin_id'];
        }

        if (isset($data['support_mode'])) {
            $mergeData['support_mode'] = $data['support_mode'];
        }

        return $this->loginById($data['id'], $mergeData);
    }

    public function logout()
    {
        $this->di['auth']->clearIdentity();
        unset($this->di['session']['devId']);
    }

    public function getRecordsPerPageList()
    {
        return $this->recordsPerPageList;
    }

    public function getMailTypes()
    {
        return ['system', 'monitoring'];
    }

    public function getSubscribes($userId)
    {
        $usersManager = $this->di['usersManager'];
        $options = $usersManager->getUserOptions($userId, \CS\Models\User\Options\UserOptionRecord::SCOPE_MAILING);
        
        $statuses = [];
        
        $types = $this->getMailTypes();
        foreach ($types as $type) {
            $optionKey = 'mail-type-' . $type . '-unsubscribed';
            
            if (isset($options[$optionKey])) {
                $statuses[$type] = ($options[$optionKey] == 0);
            } else {
                $statuses[$type] = true;
            }
        }
        
        return $statuses;
    }
    
    private function setSubscribes($userId, $active) {
        $usersManager = $this->di['usersManager'];
        
        var_dump($active);
        
        $types = $this->getMailTypes();
        foreach ($types as $type) {
            $value = 1;
            if (in_array($type, $active)) {
                $value = 0;
            }
            
            $optionKey = 'mail-type-' . $type . '-unsubscribed';
            $usersManager->setUserOption($userId, $optionKey, $value, \CS\Models\User\Options\UserOptionRecord::SCOPE_MAILING);
        }
    }

    public function setLocale($value, $update = true)
    {
        setcookie('locale', $value, time() + 3600 * 24 * 30, '/', $this->di['config']['cookieDomain']);
        if ($update && $this->di['auth']->hasIdentity()) {
            $data = $this->di['auth']->getIdentity();

            $usersManager = new UsersManager($this->getDb());
            $usersManager->getUser($data['id'])
                    ->setLocale($value)
                    ->save();
        }

        return true;
    }

    public function addSystemNote($user_id, $message)
    {
        $user_id = (int) $user_id;
        $message = $this->getDb()->quote($message);

        return $this->getDb()->exec("
            INSERT INTO users_system_notes
            SET user_id = {$user_id},
                content = {$message}");
    }

    public function addAlreadyCompletedPurchase($email)
    {
        $email = $this->getDb()->quote($email);

        return $this->getDb()->exec("
            INSERT INTO users_with_unfinished_purchase
            SET email = {$email},
              reason = 'already-completed-purchase';");
    }
    public function updateAlreadyCompletedPurchase($email)
    {
        $email = $this->getDb()->quote($email);

        return $this->getDb()->exec("
            UPDATE users_with_unfinished_purchase
            SET reason = 'already-completed-purchase'
            WHERE email = {$email};");
    }
    public function alreadyCompletedPurchaseUserExist($email)
    {
        $email = $this->getDb()->quote($email);
        
        return $this->getDb()->query("
           SELECT `reason` FROM users_with_unfinished_purchase
            WHERE `email` = {$email}")->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function checkUserLegalAcceptance($userId, $name)
    {
        $userId = (int) $userId;
        $option = $this->getDb()->quote('confirm-' . $name  . '-version-%');

        return $this->getDb()->query("
           SELECT `value` FROM users_options
            WHERE `option` LIKE  {$option} AND `user_id` = {$userId} AND `value` = 0")->fetchColumn();
    }
    public function setAuthCookie()
    {
        setcookie('s', 1, time() + 3600 * 6, '/', $this->di['config']['cookieDomain']);
    }

}
