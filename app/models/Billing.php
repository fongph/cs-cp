<?php

namespace Models;

use CS\Billing\Manager as BillingManager,
    System\FlashMessages as FlashMessages;

class Billing extends \System\Model
{

    const OPTION_CANCELLATION_DISCOUNT_OFFERED = 'cancellation-discount-offered';
    const OPTION_LICENSE_FOR_CANCELATION_DISCOUNT = 'license-for-cancellation-discount';
    const OPTION_LICENSE_WITH_CANCELATION_DISCOUNT = 'license-with-cancellation-discount';

    public function getAvailablePackages($userId)
    {
        $userId = (int) $userId;

        $result = $this->getDb()->query("
            SELECT
                lic.`id` license_id,
                p.`name`,
                p.`group` product_group,
                lic.`expiration_date`,
                CASE WHEN p.`group` LIKE '%icloud%' THEN 'icloud'
                WHEN p.`group` LIKE '%jailbreak%' THEN 'ios'
                WHEN p.`group` LIKE '%android%' THEN 'android'
                ELSE 'no'   
                END AS 'platform'
            FROM `licenses` lic
            INNER JOIN `products` p ON lic.`product_id` = p.`id`
            WHERE  lic.`user_id` = {$userId}
              AND  lic.`product_type` = 'package'
              AND  lic.`status` = 'available'");

        if ($result === false)
            return array();

        return $result->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getDataTableData($user, $params = array())
    {
        $userId = $this->getDb()->quote($user);

        $unlimitedValue = $this->getDb()->quote(\CS\Models\Limitation\LimitationRecord::UNLIMITED_VALUE);

        $select = "SELECT 
            lic.`id`, p.`name`, p.`id` as p_id, lic.`price`, lic.`currency`, lic.`amount`, lic.`price_regular`,
            lic.`activation_date`, lic.`expiration_date`, lic.`status`,
            d.`id` `deviceId`,
            d.`name` `device`,
            IF(dlim.`id` IS NULL, lim.`sms`, dlim.`sms`) `sms`,
            IF(dlim.`id` IS NULL, lim.`call`, dlim.`call`) `call`,
            IF(o.trial IS NULL, 0, o.trial) `trial`,
            (SELECT
                    MAX(`expiration_date`)
                FROM `licenses` smsl
                INNER JOIN `products` smsp ON smsl.`product_id` = smsp.`id`
                INNER JOIN `limitations` smslim ON smslim.`id` = smsp.`limitation_id`
                WHERE
                    smsl.`device_id` = lic.`device_id` AND
                    smsl.`product_type` = 'option' AND
                    smslim.sms = {$unlimitedValue}
            ) as `sms_expire_date`,
            (SELECT
                    MAX(`expiration_date`)
                FROM `licenses` callsl
                INNER JOIN `products` callsp ON callsl.`product_id` = callsp.`id`
                INNER JOIN `limitations` callslim ON callslim.`id` = callsp.`limitation_id`
                WHERE
                    callsl.`device_id` = lic.`device_id` AND
                    callsl.`product_type` = 'option' AND
                    callslim.call = {$unlimitedValue}
            ) as `calls_expire_date`,
                CASE WHEN p.`group` LIKE '%icloud%' THEN 'icloud'
                        WHEN p.`group` LIKE '%jailbreak%' THEN 'ios'
                          WHEN p.`group` LIKE '%android%' THEN 'android'
                          ELSE 'no' 
                        END AS 'platform',
                        p.code_fastspring,
				        CASE WHEN p.`code_fastspring` LIKE 'pumpic-basic-%m-%' THEN 'basic'
                       WHEN p.`code_fastspring` LIKE '%pumpic-%-1m-%' THEN 'premium-1m'
                       ELSE '-' 
                     END AS 'product_version'";

        $fromWhere = "FROM `licenses` lic
                            INNER JOIN `products` p ON lic.`product_id` = p.`id`
                            INNER JOIN `limitations` lim ON p.`limitation_id` = lim.`id`
                            LEFT JOIN `devices` d ON d.`id` = lic.`device_id`
                            LEFT JOIN `devices_limitations` dlim ON dlim.`device_id` = lic.`device_id`
                            LEFT JOIN `orders_products` op ON op.`id` = lic.`order_product_id`
                            LEFT JOIN `orders` o ON o.id = op.order_id
                            WHERE
                                lic.`user_id` = {$userId} AND
                                lic.`product_type` = 'package'";

        if ($params['active']) {
            $fromWhere .= " AND (lic.`status` = 'active' OR lic.`status` = 'available')";
        }

        $query = "{$select} {$fromWhere}" . " LIMIT {$params['start']}, {$params['length']}";
//        echo $query;
        $result = array(
            'aaData' => $this->getDb()->query($query)->fetchAll(\PDO::FETCH_ASSOC)
        );

        if (empty($result['aaData'])) {
            $result['iTotalRecords'] = 0;
            $result['iTotalDisplayRecords'] = 0;
        } else {
            $result['iTotalRecords'] = $this->getDb()->query("SELECT COUNT(*) {$fromWhere}")->fetchColumn();

            $result['iTotalDisplayRecords'] = $result['iTotalRecords'];
        }

        return $result;
    }

    public function hasActivePackages($userId)
    {
        $user = $this->getDb()->quote($userId);
        $productType = $this->getDb()->quote(\CS\Models\Product\ProductRecord::TYPE_PACKAGE);
        $licenseStatus = $this->getDb()->quote(\CS\Models\License\LicenseRecord::STATUS_ACTIVE);

        return $this->getDb()->query("SELECT `id` FROM `licenses` WHERE `product_type` = {$productType} AND `user_id` = {$user} AND `status` = {$licenseStatus} LIMIT 1")->fetchColumn() > 0;
    }

    public function getBundlesList($userId)
    {
        $user = $this->getDb()->quote($userId);
        $productType = $this->getDb()->quote(\CS\Models\Product\ProductRecord::TYPE_BUNDLE);
        $licenseStatus = $this->getDb()->quote(\CS\Models\License\LicenseRecord::STATUS_ACTIVE);
        $namespace = $this->getDb()->quote($this->di['config']['bundlesNamespace']);

        return $this->getDb()->query("SELECT
                                            p.`name`,
                                            p.`group`,
                                            b.`expiration_date`,
                                            b.`continuous`
                                        FROM `products` p
                                        LEFT JOIN (
                                                SELECT
                                                    p.`group`,
                                                    MAX(lic.`expiration_date`) `expiration_date`,
                                                    lim.`continuous`
                                                FROM `licenses` lic
                                                INNER JOIN `products` p ON lic.`product_id` = p.`id`
                                                INNER JOIN `limitations` lim ON p.`limitation_id` = lim.`id`
                                                WHERE
                                                    lic.`product_type` = {$productType} AND
                                                    lic.`status` = {$licenseStatus} AND
                                                    lic.`user_id` = {$user}
                                                GROUP BY `group`
                                            ) b ON b.`group` = p.`group`
                                        WHERE
                                            p.`type` = {$productType} AND
                                            p.`active` = 1 AND
                                            p.`namespace` = {$namespace}")->fetchAll();
    }

    public function getLicenseDeviceInfo($licId)
    {
        $licId = (int) $licId;
        return $this->getDb()->query("
            SELECT *,
                l.id license_id,
                d.id dev_id,
                d.name device_name,
                p.id product_id,
                p.name product_name
            FROM licenses l
            JOIN devices d ON l.device_id = d.id
            JOIN products p ON l.product_id = p.id
            WHERE l.id = {$licId}"
                )->fetch(\PDO::FETCH_ASSOC);
    }

    public function getUserLicenseInfo($userId, $licenseId)
    {
        $user = $this->getDb()->quote($userId);
        $license = $this->getDb()->quote($licenseId);
        $option = $this->getDb()->quote(self::OPTION_LICENSE_WITH_CANCELATION_DISCOUNT);

        $result = $this->getDb()->query("SELECT
                        l.`id`,
                        p.`name`,
                        l.`amount`,
                        l.`currency`,
                        l.`price`,
                        l.`price_regular`,
                        l.`activation_date`,
                        l.`expiration_date`,
                        l.`status`,
                        s.`payment_method` subscription_payment_method,
                        s.`reference_number` subscription_reference_number,
                        s.`auto` subscription_cancelable,
                        s.`url` subscription_url,
                        (SELECT COUNT(*) FROM `users_options` WHERE `user_id` = l.`user_id` AND `option` = {$option} AND value = l.`id` LIMIT 1) has_cancelation_discount
                    FROM `licenses` l
                    INNER JOIN `products` p ON p.`id` = l.`product_id`
                    LEFT JOIN `subscriptions` s ON l.`id` = s.`license_id`
                    WHERE
                        l.`id` = {$license} AND
                        l.`user_id` = {$user}
                    LIMIT 1")->fetch();

        if ($result !== false) {
            $withDiscount = round($result['price_regular'] * 0.8, 2);
            $result['price_with_cancelation_discount'] = number_format($withDiscount, 2, '.', '');
        }

        return $result;
    }

    private function getLicenseSubscriptionInfo($licenseId)
    {
        try {
            return $this->di['billingManager']->getLicenseSubscriptionInfo($licenseId);
        } catch (\CS\Billing\Exceptions\RecordNotFoundException $e) {
            $this->getDI()->get('logger')->addInfo('Subscription not found!', array('exception' => $e));
        } catch (\CS\Billing\Exceptions\GatewayException $e) {
            $this->getDI()->get('logger')->addWarning('Gateway request was not successfuly completed!', array('exception' => $e, 'gatewayResponse' => $e->getResponse()->getMessage()));
        } catch (\Seller\Exception\SellerException $e) {
            $this->getDI()->get('logger')->addError('Gateway exception!', array('exception' => $e));
        }
    }

    public function isCancelationDiscountOffered()
    {
        $authData = $this->di['auth']->getIdentity();

        if (isset($authData['options'][self::OPTION_CANCELLATION_DISCOUNT_OFFERED])) {
            return true;
        }

        $usersManager = $this->di['usersManager'];
        $value = $usersManager->getUserOption($authData['id'], self::OPTION_CANCELLATION_DISCOUNT_OFFERED);

        if ($value === false) {
            return false;
        }

        return true;
    }

    public function isCancelationDiscountOfferableForLicense($license)
    {
        // only for fastspring
        if ($license['subscription_payment_method'] !== \CS\Models\Order\OrderRecord::PAYMENT_METHOD_FASTSPRING) {
            return false;
        }

        // only for active subscriptions
        if (!$license['subscription_cancelable']) {
            return false;
        }

        if ($this->isCancelationDiscountOffered()) {
            return false;
        }

        $authData = $this->di['auth']->getIdentity();

        if (isset($authData[self::OPTION_LICENSE_FOR_CANCELATION_DISCOUNT])) {
            $licenseForDiscount = $authData['options'][self::OPTION_LICENSE_FOR_CANCELATION_DISCOUNT];
        } else {
            $usersManager = $this->di['usersManager'];
            $licenseForDiscount = $usersManager->getUserOption($authData['id'], self::OPTION_LICENSE_FOR_CANCELATION_DISCOUNT);
        }

        if ($licenseForDiscount > 0 && $license['id'] != $licenseForDiscount) {
            return false;
        }

        return true;
    }

    public function setLicenseForCancelationDiscount($userId, $licenseId)
    {
        $usersManager = $this->di['usersManager'];
        $usersManager->setUserOption($userId, self::OPTION_LICENSE_FOR_CANCELATION_DISCOUNT, $licenseId);
    }

    public function setCancelationDiscountOffered($userId)
    {
        $usersManager = $this->di['usersManager'];
        $usersManager->setUserOption($userId, self::OPTION_CANCELLATION_DISCOUNT_OFFERED, 1);
    }

    public function setLicenseWithCancelationDiscount($userId, $licenseId)
    {
        $usersManager = $this->di['usersManager'];
        $usersManager->setUserOption($userId, self::OPTION_LICENSE_WITH_CANCELATION_DISCOUNT, $licenseId);
    }
    
    public function enableLicenseAutorebill($licenseId) {
        $billingManager = $this->di['billingManager'];
        
        $subscriptionRecord = $billingManager->getLicenseSubscription($licenseId);
                
        $subscriptionIterator = $billingManager->getSubscriptionsIterator($subscriptionRecord->getReferenceNumber(), $subscriptionRecord->getPaymentMethod());
        
        foreach ($subscriptionIterator as $subscriptionRecord) {
            $licenseRecord = $subscriptionRecord->getLicense();
            
            $this->di['usersNotesProcessor']->licenseSubscriptionAutoRebillTaskAdded($licenseRecord->getId());
            $this->di['billingManager']->unCancelLicenseSubscription($licenseRecord->getId());
        }
    }
    
    public function disableLicenseAutorebill($licenseId) {
        $billingManager = $this->di['billingManager'];
        
        $subscriptionRecord = $billingManager->getLicenseSubscription($licenseId);
                
        $subscriptionIterator = $billingManager->getSubscriptionsIterator($subscriptionRecord->getReferenceNumber(), $subscriptionRecord->getPaymentMethod());
        
        foreach ($subscriptionIterator as $subscriptionRecord) {
            $licenseRecord = $subscriptionRecord->getLicense();
            
            $this->di['usersNotesProcessor']->licenseSubscriptionAutoRebillTaskAdded($licenseRecord->getId());
            $this->di['billingManager']->cancelLicenseSubscription($licenseRecord->getId());
        }
    }

}
