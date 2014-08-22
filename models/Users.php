<?php

namespace Models;

use Hautelook\Phpass\PasswordHash;

class Users extends \System\Model {

    protected $_restoreSecretKey = '87%*5gJ';
    protected $_simpleLoginSecretKey = '~l\OF#chpB;GcOiSJ';
    protected $_recordsPerPageList = array(10, 25, 50, 100);
    static $loginAttempts = 5;
    static $loginAttemptsPeriod = 300; // 5 min

    public function __construct($di) {
        parent::__construct($di);
    }

    public function getHash($value) {
        $passwordHash = new PasswordHash(8, true);

        return $passwordHash->HashPassword($value);
    }

    public function checkPassword($password, $hash) {
        $passwordHash = new PasswordHash(8, true);

        return $passwordHash->CheckPassword($password, $hash);
    }

    public function login($email, $password) {
        $email = $this->getDb()->quote($email);

        if (($data = $this->getDb()
                ->query("SELECT * FROM `users` WHERE `login` = {$email} LIMIT 1")
                ->fetch()) === false) {

            throw new UsersInvalidEmail();
        }

        if ($data['locked']) {
            throw new UsersAccountLocked();
        }

        if (!$this->checkPassword($password, $data['password'])) {
            if ($this->getLoginAttemptsCount($data['id']) >= self::$loginAttempts - 1) {
                $this->lock($data['id'], $data['login']);
                throw new UsersAccountLocked();
            }

            $this->addLoginAttempt($data['id']);

            throw new UsersInvalidPassword();
        }

        $this->removeLoginAttempts($data['id']);
        $this->logAuth($data['id']);
        $this->di['auth']->setIdentity($data);
        $this->setLocale($data['locale'], false);
        return true;
    }

    public function getLoginAttemptsCount($userId) {
        $minTime = time() - self::$loginAttemptsPeriod;
        $userId = intval($userId);

        return $this->getDb()->query("SELECT COUNT(*) FROM `users_auth_attempts` WHERE `user_id` = {$userId} AND `time` > {$minTime}")->fetchColumn();
    }

    public function addLoginAttempt($userId) {
        $userId = intval($userId);
        return $this->getDb()->exec("INSERT INTO `users_auth_attempts` SET `user_id` = {$userId}");
    }

    public function lock($userId, $email) {
        $userId = intval($userId);

        $secretString = $this->_buildRestoreString($email);

        $secretValue = $this->getDb()->quote($secretString);
        if (!$this->getDb()->exec("UPDATE `users` SET `lock` = {$secretValue}, `locked` = 1 WHERE `id` = {$userId}")) {
            throw new \Exception('Error during lock user!');
        }

        $mailModel = new \Models\Mail($this->di);
        $mailModel->sendUnlockPassword($email, $this->di['router']->getRouteUrl('unlockAccount') . '?' . http_build_query(array(
                    'email' => $email,
                    'key' => $secretString
        )));

        $this->removeLoginAttempts($userId);
    }

    public function removeLoginAttempts($userId) {
        $userId = intval($userId);
        return $this->getDb()->exec("DELETE FROM `users_auth_attempts` WHERE `user_id` = {$userId}");
    }

    public function simpleLogin($id, $hash) {
        $id = intval($id);

        if ((($email = $this->getDb()->query("SELECT `login` FROM `users` WHERE `id` = {$id} LIMIT 1")->fetchColumn()) !== false) &&
                ($this->_buildSimpleLoginString($id, $email) == $hash)) {
            $this->loginById($id);
            return true;
        }

        return false;
    }

    public function simpleRestorePassword($id, $hash) {
        $id = intval($id);

        if ((($email = $this->getDb()->query("SELECT `login` FROM `users` WHERE `id` = {$id} LIMIT 1")->fetchColumn()) !== false) &&
                ($this->_buildSimpleLoginString($id, $email) == $hash)) {
            $this->lostPasswordSend($email);
            return true;
        }

        return false;
    }

    public function simpleCreatePassword($id, $hash, $old, $new) {
        $id = intval($id);
        $old = $this->getDb()->quote($old);

        if ((($email = $this->getDb()->query("SELECT `login` FROM `users` WHERE `id` = {$id} AND `password` = {$old} LIMIT 1")->fetchColumn()) !== false) &&
                ($this->_buildSimpleLoginString($id, $email) == $hash)) {
            $new = $this->getDb()->quote($this->getHash($new));

            return $this->getDb()->exec("UPDATE `users` SET `password` = {$new} WHERE `id` = {$id} LIMIT 1") == 1;
        }

        return false;
    }

    private function _buildSimpleLoginString($id, $email) {
        return md5($id . $email . $this->_simpleLoginSecretKey);
    }

    public function loginById($id) {
        $id = intval($id);
        if (($data = $this->getDb()->query("SELECT * FROM `users` WHERE `id` = {$id} LIMIT 1")->fetch()) != false) {
            $this->di['auth']->setIdentity($data);
            $this->setLocale($data['locale'], false);
            return true;
        }
        return false;
    }

    public function reLogin() {
        $data = $this->di['auth']->getIdentity();
        return $this->loginById($data['id']);
    }

    public function logout() {
        $this->di['auth']->clearIdentity();
    }

    public function isPassword($value) {
        $data = $this->di['auth']->getIdentity();
        return $this->checkPassword($value, $data['password']);
    }

    public function changePassword($value) {
        $data = $this->di['auth']->getIdentity();

        $id = $data['id'];
        $password = $this->getDb()->quote($this->getHash($value));
        return $this->getDb()
                        ->exec("UPDATE `users` SET `password`={$password} WHERE `id`={$id}");
    }

    public function getRecordsPerPageList() {
        return $this->_recordsPerPageList;
    }

    public function updateSettings($data) {
        $this->setLocale($data['locale']);
        $this->setRecordsPerPage($data['recordsPerPage']);
        return $this->reLogin();
    }

    public function setRecordsPerPage($value) {
        if ($this->di['auth']->hasIdentity()) {
            if (!in_array($value, $this->_recordsPerPageList)) {
                $value = $this->_recordsPerPageList[0];
            }

            $data = $this->di['auth']->getIdentity();
            return $this->getDb()->exec("UPDATE `users` SET `records_per_page`={$value} WHERE `id`={$data['id']}");
        }

        return false;
    }

    public function setLocale($value, $update = true) {
        setcookie('locale', $value, time() + 3600 * 24 * 30, '/');
        if ($update && $this->di['auth']->hasIdentity()) {
            $locale = $this->getDb()->quote($value);
            $data = $this->di['auth']->getIdentity();

            return $this->getDb()->exec("UPDATE `users` SET `locale`={$locale} WHERE `id`={$data['id']}");
        }
    }

    public function isUser($email) {
        $email = $this->getDb()->quote($email);
        return $this->getDb()->query("SELECT COUNT(*) FROM `users` WHERE `login` = {$email} LIMIT 1")->fetchColumn() > 0;
    }

    protected function _buildRestoreString($email) {
        return md5($email . time() . $this->_restoreSecretKey);
    }

    public function lostPasswordSend($email) {
        if (!$this->isUser($email)) {
            throw new UsersEmailNotFoundException();
        }

        $secretString = $this->_buildRestoreString($email);
        $emailValue = $this->getDb()->quote($email);
        $secretValue = $this->getDb()->quote($secretString);
        if (!$this->getDb()->exec("UPDATE `users` SET `restore` = {$secretValue} WHERE `login` = {$emailValue}")) {
            throw new Exception('Error during set restore key!');
        }

        $resetUrl = $this->di['router']->getRouteUrl('resetPassword') . '?' . http_build_query(array(
                    'email' => $email,
                    'key' => $secretString,
        ));

        $mailModel = new \Models\Mail($this->di);
        $mailModel->sendRestorePassword($email, array(
            'resetUrl' => $resetUrl
        ));
    }

    public function canRestorePassword($email, $secretString) {
        $email = $this->getDb()->quote($email);
        $secretString = $this->getDb()->quote($secretString);
        return $this->getDb()->query("SELECT COUNT(*) FROM `users` WHERE `login` = {$email} AND `restore` = {$secretString} LIMIT 1")->fetchColumn() > 0;
    }

    public function unlockAccount($email, $secretString) {
        $email = $this->getDb()->quote($email);
        $secretString = $this->getDb()->quote($secretString);
        return $this->getDb()->exec("UPDATE `users` SET `locked` = 0 WHERE `login` = {$email} AND `lock` = {$secretString} LIMIT 1") > 0;
    }

    public function resetPassword($email, $newPassword, $newPasswordConfirm) {
        if ($newPassword !== $newPasswordConfirm) {
            throw new UsersPasswordsNotEqualException();
        }

        if (strlen($newPassword) < 6) {
            throw new UsersPasswordTooShortException();
        }

        $password = $this->getDb()->quote($this->getHash($newPassword));
        $emailValue = $this->getDb()->quote($email);
        if (!$this->getDb()->exec("UPDATE `users` SET `restore` = '' AND `password` = {$password} WHERE `login` = {$emailValue}")) {
            throw new Exception('Error during set restore key!');
        }
    }

    public function logAuth($id) {
        $info = get_browser();

        $result = array(
            'ip' => '',
            'country' => '',
            'browser' => '',
            'browserVersion' => '',
            'platform' => '',
            'platformVersion' => '',
            'mobile' => 0,
            'tablet' => 0
        );

        $result['ip'] = getRealIp();
        $result['country'] = getIPCountry($result['ip']);

        if (isset($info->browser, $info->version)) {
            $result['browser'] = $info->browser;
            $result['browserVersion'] = $info->version;
        }

        if (isset($info->platform, $info->platform_version)) {
            $result['platform'] = $info->platform;
            $result['platformVersion'] = $info->platform_version;
        }

        if (isset($info->ismobiledevice)) {
            if ($info->ismobiledevice) {
                $result['mobile'] = 1;
            } else {
                $result['mobile'] = 0;
            }
        }

        if (isset($info->istablet)) {
            if ($info->istablet) {
                $result['tablet'] = 1;
            } else {
                $result['tablet'] = 0;
            }
        }

        $id = intval($id);
        $fullInfo = $this->getDb()->quote(@json_encode($info));
        $result['ip'] = $this->getDb()->quote($result['ip']);
        $result['country'] = $this->getDb()->quote($result['country']);
        $result['browser'] = $this->getDb()->quote($result['browser']);
        $result['browserVersion'] = $this->getDb()->quote($result['browserVersion']);
        $result['platform'] = $this->getDb()->quote($result['platform']);
        $result['platformVersion'] = $this->getDb()->quote($result['platformVersion']);
        $result['mobile'] = $this->getDb()->quote($result['mobile']);
        $result['tablet'] = $this->getDb()->quote($result['tablet']);
        $userAgent = $this->getDb()->quote($_SERVER['HTTP_USER_AGENT']);

        $this->getDb()->exec("INSERT INTO `users_auth_log` SET 
            `user_id` = {$id},
            `ip` = {$result['ip']},
            `country` = {$result['country']},
            `browser` = {$result['browser']},
            `browser_version` = {$result['browserVersion']},
            `platform` = {$result['platform']},
            `platform_version` = {$result['platformVersion']},
            `mobile` = {$result['mobile']},
            `tablet` = {$result['tablet']},
            `user_agent` = {$userAgent},
            `full_info` = {$fullInfo}");
    }

}

class UsersEmailNotFoundException extends \Exception {
    
}

class UsersPasswordsNotEqualException extends \Exception {
    
}

class UsersPasswordTooShortException extends \Exception {
    
}

class UsersInvalidEmail extends \Exception {
    
}

class UsersInvalidPassword extends \Exception {
    
}

class UsersAccountLocked extends \Exception {
    
}
