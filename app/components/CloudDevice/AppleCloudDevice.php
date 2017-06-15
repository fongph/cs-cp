<?php

namespace Components\CloudDevice;

/**
 * Description of AppleCloudDevice
 *
 * @author orest
 */
class AppleCloudDevice extends AbstractCloudDevice {

    public function __construct($data)
    {
        $this->uniqueId = $data['id'];
        $this->name = $data['name'];
        $this->lastBackupTimestamp = $data['timestamp'];
        $this->backupSize = $data['backupSize'];
        $this->serialNumber = $data['serialNumber'];
        $this->image = $data['image']['2x'];
        $this->model = $data['deviceModel'];
        $this->modelName = $data['deviceModelName'];
    }

}
