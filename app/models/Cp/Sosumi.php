<?php

namespace Models\Cp;

// Sosumi - a PHP client for Apple's Find My iPhone web service
//
// June 20, 2010
// Tyler Hall <tylerhall@gmail.com>
// http://github.com/tylerhall/sosumi/tree/master
//
// Usage:
// $ssm = new Sosumi('username', 'password');
// $location_info = $ssm->locate(<device id>);
// $ssm->sendMessage('Your Message', true, <device id>, 'Important Message');
class Sosumi
{

    public $devices;
    public $debug;
    private $prsId;
    private $username;
    private $password;
    private $partition;
    private $clientContext = array(
        'appName' => 'FindMyiPhone',
        'appVersion' => '3.0',
        'buildVersion' => '376',
        'clientTimestamp' => 0,
        'deviceUDID' => null,
        'inactiveTime' => 1,
        'osVersion' => '7.0.3',
        'productType' => 'iPhone6,1'
    );
    private $serverContext = array(
        'callbackIntervalInMS' => 10000,
        'classicUser' => false,
        'clientId' => null,
        'cloudUser' => true,
        'deviceLoadStatus' => '200',
        'enableMapStats' => false,
        'isHSA' => false,
        'lastSessionExtensionTime' => null,
        'macCount' => 0,
        'maxDeviceLoadTime' => 60000,
        'maxLocatingTime' => 90000,
        'preferredLanguage' => 'en-us',
        'prefsUpdateTime' => 0,
        'sessionLifespan' => 900000,
        'timezone' => null,
        'trackInfoCacheDurationInSecs' => 86400,
        'validRegion' => true
    );

    public function __construct($mobile_me_username, $mobile_me_password, $debug = false)
    {
        $this->devices = array();
        $this->debug = $debug;
        $this->username = $mobile_me_username;
        $this->password = $mobile_me_password;
        $this->getPartition();
        $this->initClient();
        $this->refreshClient();
    }

    private function getPartition()
    {
        $this->iflog('Getting partition...');

        $post = json_encode(array(
            'clientContext' => $this->clientContext
        ));

        $response = $this->curlPost("/fmipservice/device/{$this->username}/initClient", $post, array(), true);
        preg_match('/MMe-Host:(.*?)$/msi', $response, $matches);
        
        if (isset($matches[1]))
            $this->partition = trim($matches[1]);
    }

    public function refreshClient()
    {
        $this->iflog('refreshClient ' . $this->prsId);

        $post = json_encode(array(
            'clientContext' => $this->clientContext
        ));

        $response = $this->curlPost("/fmipservice/device/{$this->prsId}/refreshClient", $post, array(), true);
    }

    public function locate($device_id, $max_wait = 300)
    {
        $start = time();
        // Loop until the device has been located...

        while (!isset($this->devices[$device_id]->latitude, $this->devices[$device_id]->longitude)) {
            $this->iflog('Waiting for location...');
            if ((time() - $start) > $max_wait) {
                throw new SosumiException("Unable to find location within '$max_wait' seconds\n");
            }
            sleep(5);
            $this->initClient();
        }
        
        $loc = array(
            "latitude" => $this->devices[$device_id]->latitude,
            "longitude" => $this->devices[$device_id]->longitude,
            "accuracy" => $this->devices[$device_id]->horizontalAccuracy,
            "timestamp" => $this->devices[$device_id]->locationTimestamp,
        );
        
        return $loc;
    }

    public function sendMessage($device_id, $msg, $alarm = false, $subject = 'Important Message')
    {
        $post = sprintf('{"clientContext":{"appName":"FindMyiPhone","appVersion":"1.4","buildVersion":"145","deviceUDID":"0000000000000000000000000000000000000000","inactiveTime":5911,"osVersion":"3.2","productType":"iPad1,1","selectedDevice":"%s","shouldLocate":false},"device":"%s","serverContext":{"callbackIntervalInMS":3000,"clientId":"0000000000000000000000000000000000000000","deviceLoadStatus":"203","hasDevices":true,"lastSessionExtensionTime":null,"maxDeviceLoadTime":60000,"maxLocatingTime":90000,"preferredLanguage":"en","prefsUpdateTime":1276872996660,"sessionLifespan":900000,"timezone":{"currentOffset":-25200000,"previousOffset":-28800000,"previousTransition":1268560799999,"tzCurrentName":"Pacific Daylight Time","tzName":"America/Los_Angeles"},"validRegion":true},"sound":%s,"subject":"%s","text":"%s","userText":true}', $device_id, $device_id, $alarm ? 'true' : 'false', $subject, $msg);
        $this->iflog('Sending message...');
        $this->curlPost("/fmipservice/device/{$this->username}/sendMessage", $post);
        $this->iflog('Message sent');
    }

    public function remoteLock($device_id, $passcode)
    {
        $post = sprintf('{"clientContext":{"appName":"FindMyiPhone","appVersion":"1.4","buildVersion":"145","deviceUDID":"0000000000000000000000000000000000000000","inactiveTime":5911,"osVersion":"3.2","productType":"iPad1,1","selectedDevice":"%s","shouldLocate":false},"device":"%s","oldPasscode":"","passcode":"%s","serverContext":{"callbackIntervalInMS":3000,"clientId":"0000000000000000000000000000000000000000","deviceLoadStatus":"203","hasDevices":true,"lastSessionExtensionTime":null,"maxDeviceLoadTime":60000,"maxLocatingTime":90000,"preferredLanguage":"en","prefsUpdateTime":1276872996660,"sessionLifespan":900000,"timezone":{"currentOffset":-25200000,"previousOffset":-28800000,"previousTransition":1268560799999,"tzCurrentName":"Pacific Daylight Time","tzName":"America/Los_Angeles"},"validRegion":true}}', $device_id, $device_id, $passcode);
        $this->iflog('Sending remote lock...');
        $this->curlPost("/fmipservice/device/{$this->username}/remoteLock", $post);
        $this->iflog('Remote lock sent');
    }

    // This hasn't been tested (for obvious reasons). Please let me know if it does/doesn't work...
    public function remoteWipe($device_id, $passcode)
    {
        $post = sprintf('{"clientContext":{"appName":"FindMyiPhone","appVersion":"1.4","buildVersion":"145","deviceUDID":"0000000000000000000000000000000000000000","inactiveTime":5911,"osVersion":"3.2","productType":"iPad1,1","selectedDevice":"%s","shouldLocate":false},"device":"%s","oldPasscode":"","passcode":"%s","serverContext":{"callbackIntervalInMS":3000,"clientId":"0000000000000000000000000000000000000000","deviceLoadStatus":"203","hasDevices":true,"lastSessionExtensionTime":null,"maxDeviceLoadTime":60000,"maxLocatingTime":90000,"preferredLanguage":"en","prefsUpdateTime":1276872996660,"sessionLifespan":900000,"timezone":{"currentOffset":-25200000,"previousOffset":-28800000,"previousTransition":1268560799999,"tzCurrentName":"Pacific Daylight Time","tzName":"America/Los_Angeles"},"validRegion":true}}', $device_id, $device_id, $passcode);
        $this->iflog('Sending remote wipe...');
        $this->curlPost("/fmipservice/device/{$this->username}/remoteWipe", $post);
        $this->iflog('Remote wipe sent');
    }

    private function initClient()
    {
        $this->iflog('initClient...');

        $post = json_encode(array(
            'clientContext' => $this->clientContext
        ));

        $json_str = $this->curlPost("/fmipservice/device/{$this->username}/initClient", $post);
        $this->iflog('initClient Returned: ' . $json_str);
        $json = json_decode($json_str, true);
        if (is_null($json))
            throw new SosumiException("Error parsing json string");
        if (isset($json->error))
            throw new SosumiException("Error from web service: '$json->error'");
        
        $this->devices = array();

        if (isset($json) && isset($json['content']) && (is_array($json['content']))) {
            $this->prsId = $json['serverContext']['prsId'];
            $this->iflog('Parsing ' . count($json['content']) . ' devices...');
            foreach ($json['content'] as $device) {
                $this->devices[$device['id']] = $device;
            }
        }
    }

    private function curlPost($url, $post_vars = '', $headers = array(), $return_headers = false)
    {
        if (isset($this->partition))
            $url = 'https://' . $this->partition . $url;
        else
            $url = 'https://fmipmobile.icloud.com' . $url;
        $this->iflog("URL: $url");
        $this->iflog("POST DATA: $post_vars");
        $headers[] = 'Content-Type: application/json; charset=utf-8';
        $headers[] = 'X-Apple-Find-Api-Ver: 2.0';
        $headers[] = 'X-Apple-Authscheme: UserIdGuest';
        $headers[] = 'X-Apple-Realm-Support: 1.0';
        $headers[] = 'User-agent: Find iPhone/1.4 MeKit (iPad: iPhone OS/4.2.1)';
        $headers[] = 'X-Client-Name: iPad';
        $headers[] = 'X-Client-UUID: 0cf3dc501ff812adb0b202baed4f37274b210853';
        $headers[] = 'Accept-Language: en-us';
        $headers[] = "Connection: keep-alive";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vars);
        if (!is_null($headers))
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // curl_setopt($ch, CURLOPT_VERBOSE, true);
        if ($return_headers)
            curl_setopt($ch, CURLOPT_HEADER, true);
        return curl_exec($ch);
    }

    private function iflog($str)
    {
        if ($this->debug === true)
            echo $str . "\n";
    }

}

class SosumiException extends \Exception
{
    
}
