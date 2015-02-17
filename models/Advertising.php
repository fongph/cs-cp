<?php

namespace Models;

use CS\Models\License\LicenseRecord;

/**
 * Description of Advertising
 *
 * @author orest
 */
class Advertising extends \System\Model
{

    public function showInternalTrialLicenseProlongBanner($licenseId)
    {
        $licenseId = $this->getDb()->quote($licenseId);
        $statusAvailable= $this->getDb()->quote(LicenseRecord::STATUS_AVAILABLE);
        $statusActive= $this->getDb()->quote(LicenseRecord::STATUS_ACTIVE);
        $lifetime = $this->getDb()->quote(time() + 3 * 24 * 3600);
        
        return $this->getDb()->query("SELECT id FROM `licenses` WHERE id = {$licenseId} AND (`status` = {$statusActive} OR `status` = {$statusAvailable}) AND `lifetime` < {$lifetime} LIMIT 1")->fetchColumn() !== false;
    }

}
