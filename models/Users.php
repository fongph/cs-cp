<?php

namespace Models;

use System\Model,
    System\Session,
    CS\Users\UsersManager,
    CS\Models\User\UserRecord;

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

    public function simpleLogin($id, $hash)
    {
        $id = intval($id);

        if ((($email = $this->getDb()->query("SELECT `user_login` FROM `g1_users` WHERE `id` = {$id} LIMIT 1")->fetchColumn()) !== false) &&
                ($this->_buildSimpleLoginString($id, $email) == $hash)) {
            $this->loginById($id);
            return true;
        }

        return false;
    }

    public function loginById($id)
    {
        $usersManager = new UsersManager($this->getDb());

        $data = $usersManager->loginById($id);

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
    }

    public function getRecordsPerPageList()
    {
        return $this->recordsPerPageList;
    }

    public function setLocale($value, $update = true)
    {
        setcookie('locale', $value, time() + 3600 * 24 * 30, '/');
        if ($update && $this->di['auth']->hasIdentity()) {
            $data = $this->di['auth']->getIdentity();

            $usersManager = new UsersManager($this->getDb());
            $usersManager->getUser($data['id'])
                    ->setLocale($value)
                    ->save();
        }

        return true;
    }

}
