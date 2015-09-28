<?php

namespace Models\Cp;

use CS\Devices\DeviceOptions;

class Info extends BaseModel
{

    public function getDeviceInfo($devId)
    {
        $escapedDevId = $this->getDB()->quote($devId);

        return $this->getDb()->query("SELECT * FROM `dev_info` WHERE `dev_id` = {$escapedDevId} LIMIT 1")->fetch();
    }

    public function getInfo($devId)
    {
        $devInfo = $this->di['currentDevice'];

        if (($info = $this->getDeviceInfo($devId)) === false) {
            throw new Settings\SettingsNotFoundException("Device info not found");
        }

        $internal = ($info['int_storage_total'] && $info['int_storage_free'])? [
                'total' => $info['int_storage_total']? self::formatBytes($info['int_storage_total']) : null,
                'free' => $info['int_storage_free']? self::formatBytes($info['int_storage_free']) : null,
            ] : null;

        $external = ($info['ext_storage_total'] && $info['ext_storage_free'])? [
            'total' => $info['ext_storage_total']? self::formatBytes($info['ext_storage_total']) : null,
            'free' => $info['ext_storage_free']? self::formatBytes($info['ext_storage_free']) : null,
        ] : null;

        return array(
            'info' => $info,
            'internal' => $internal,
            'external' => $external,
            'carrier' => $info['carrier']? explode('_', $info['carrier'])[0] : null
        );
    }

    public static function formatBytes($bytes, $precision = 3) {
        $base = log($bytes, 1024);
        $suffixes = array('', 'kB', 'MB', 'GB', 'TB');

        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }

}
