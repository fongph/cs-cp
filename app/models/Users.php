<?php

namespace Models;

use System\Model,
    System\Session,
    CS\Users\UsersManager,
    CS\Models\User\UserRecord,
    CS\Settings\GlobalSettings;

class Users extends Model
{

    protected $recordsPerPageList = array(10, 25, 50, 100);

    public function __construct($di)
    {
        parent::__construct($di);
    }

    public function getUserRecord()
    {
        return new UserRecord($this->getDb());
    }

    public function getUsersManager()
    {
        return new UsersManager($this->getDb());
    }

    public function login($email, $password, $remember = false)
    {
        $usersManager = new UsersManager($this->getDb());
        $usersManager->setSender($this->di['mailSender']);

        $data = $usersManager->login($this->di['config']['site'], $email, $password);

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

    public function directLogin($userId, $adminId, $hash)
    {
        $usersManager = $this->getUsersManager();

        try {
            $data = $usersManager->getDirectLoginUserData(
                    $this->di['config']['site'], $userId, $adminId, $hash, GlobalSettings::getDirectLoginSalt($this->di['config']['site'])
            );
        } catch (\CS\Users\DirectLoginException $e) {
            $this->di['logger']->addAlert("Direct Login Error: " . $e->getMessage());
            return false;
        }

        $this->di['auth']->setIdentity($data);
        $this->setLocale($data['locale'], false);
        
        return true;
    }

    public function loginById($id)
    {
        $usersManager = $this->getUsersManager();

        $data = $usersManager->getUserDataById($this->di['config']['site'], $id);

        $this->di['auth']->setIdentity($data);

        $this->setLocale($data['locale'], false);

        return true;
    }

    public function reLogin()
    {
        $data = $this->di['auth']->getIdentity();
        return $this->loginById($data['id']);
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
        $user_id = (int)$user_id;
        $message = $this->getDb()->quote($message);

        return $this->getDb()->exec("
            INSERT INTO users_system_notes
            SET user_id = {$user_id},
                content = {$message}");
    }
    
    public function setAuthCookie() {
        setcookie('s', 1, time() + 3600 * 6, '/', $this->di['config']['cookieDomain']);
    }
    
}
