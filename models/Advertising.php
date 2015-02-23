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

    public function getInternalTrialLicenseDaysLeft($userId, $licenseId)
    {
        if (!$this->isInternalTrialLicenseRecordExists($userId)) {
            return false;
        }
        
        $licenseId = $this->getDb()->quote($licenseId);
        $statusAvailable = $this->getDb()->quote(LicenseRecord::STATUS_AVAILABLE);
        $statusActive = $this->getDb()->quote(LicenseRecord::STATUS_ACTIVE);

        $data = $this->getDb()->query("SELECT `lifetime`, `status` FROM `licenses` WHERE id = {$licenseId} LIMIT 1")->fetch();
        
        if ($data === false) {
            return 0;
        }

        $timeleft = $data['lifetime'] - time();

        if ($timeleft > 0) {
            return ceil($timeleft / (24 * 3600));
        } else if ($data['status'] !== LicenseRecord::STATUS_INACTIVE) {
            return 1;
        }

        return 0;
    }

    private function isInternalTrialLicenseRecordExists($userId)
    {
        $userId = $this->getDb()->quote($userId);
        
        return $this->getDb()->query("SELECT COUNT(*) FROM `users_options` WHERE user_id = {$userId} AND `option` = 'internal-trial-license' LIMIT 1")->fetchColumn() > 0;
    }

}
