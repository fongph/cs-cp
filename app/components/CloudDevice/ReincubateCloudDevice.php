<?php

namespace Components\CloudDevice;

/**
 * Description of ReincubateCloudDevice
 *
 * @author orest
 */
class ReincubateCloudDevice extends AbstractCloudDevice {

    protected $reincubateDeviceId;

    public function __construct($data)
    {
        $this->uniqueId = $data['device_tag'];
        $this->color = $data['colour'];
        $this->os = 'ios';
        $this->osVersion = $data['ios_version'];
        $this->name = $data['device_name'];
        $this->lastBackupTimestamp = self::toTimestamp($data['latest-backup']);
        $this->model = $data['model'];
        $this->modelName = $data['name'];
        $this->serialNumber = $data['serial'];
        $this->reincubateDeviceId = $data['device_id'];
    }

    public function getReincubateDeviceId()
    {
        return $this->reincubateDeviceId;
    }

    private static function toTimestamp($date)
    {
        $a = \DateTime::createFromFormat('Y-m-d H:i:s.u', $date, new \DateTimeZone('UTC'));
        return $a->getTimestamp();
    }

}
