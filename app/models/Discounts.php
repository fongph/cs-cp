<?php

namespace Models;

use CS\Devices\Manager as DevicesManager,
    CS\Devices\Limitations;
use CS\Devices\Manager;
use CS\Models\Device\DeviceRecord;
use CS\Models\License\LicenseRecord;
use CS\Models\Discount\DiscountRecord;

class Discounts extends \System\Model
{

    public function setDiscount($user_id, $license_id, $status = DiscountRecord::DISCOUNT_APPLE)
    {
        if(!$license_id)
            throw new Discounts\InvalidDiscountsLicenceException();
        
        $discountRecord = new DiscountRecord( $this->getDb() );
        $discountRecord->setUserId($user_id);
        $discountRecord->setLicenseId($license_id);
        $discountRecord->setStatus($status);
        $discountRecord->save();
         
    }

    public function getDiscount($userId, $license_id, $status = DiscountRecord::DISCOUNT_APPLE)
    {
        $userId = (int)$userId;
        $license_id = $this->getDb()->quote($license_id);
        $status = $this->getDb()->quote($status);

        return $this->getDb()->query("
            SELECT * FROM `discounts` WHERE 
                `user_id` = {$userId} AND
                `status` = {$status} AND    
                `license_id` = {$license_id} LIMIT 1")->fetch();
    }
    
    public function getDiscountUserId($user_id) {
        $user_id = $this->getDb()->quote($user_id);

        return $this->getDb()->query("
            SELECT * FROM `discounts` WHERE 
                `user_id` = {$user_id} LIMIT 1")->fetch();
    }
    
    public function deleteDiscount($id, $status = DiscountRecord::DISCOUNT_DELETE) {
        if(!$id)
            throw new Discounts\InvalidDiscountsLicenceException();
        
        $discountRecord = new DiscountRecord( $this->getDb() );
        $discountRecord->setId($id);
        $discountRecord->setStatus($status);
        $discountRecord->save();
    }
    
    public function completedDiscount($id, $status = DiscountRecord::DISCOUNT_COMPLETED) {
        if(!$id)
            throw new Discounts\InvalidDiscountsLicenceException();
        
        $discountRecord = new DiscountRecord( $this->getDb() );
        $discountRecord->setId($id);
        $discountRecord->setStatus($status);
        $discountRecord->save();
    }

}

class DiscountsInvalidNetworkException extends \Exception
{
    
}
