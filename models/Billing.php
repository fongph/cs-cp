<?php

namespace Models;

class Billing extends \System\Model
{

    public function getDataTableData($user, $params = array())
    {
        $userId = $this->getDb()->quote($user);

        $unlimitedValue = $this->getDb()->quote(\CS\Models\Limitation\LimitationRecord::UNLIMITED_VALUE);
        
        $select = "SELECT lic.`id`, p.`name`, lic.`amount`, lic.`currency`,
            lic.`activation_date`, lic.`expiration_date`, lic.`status`,
            d.`id` `deviceId`,
            d.`name` `device`,
            IF(dlim.`id` IS NULL, lim.`sms`, dlim.`sms`) `sms`,
            IF(dlim.`id` IS NULL, lim.`call`, dlim.`call`) `call`,
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
            ) as `calls_expire_date`";

        $fromWhere = "FROM `licenses` lic
                            INNER JOIN `products` p ON lic.`product_id` = p.`id`
                            INNER JOIN `limitations` lim ON p.`limitation_id` = lim.`id`
                            LEFT JOIN `devices` d ON d.`id` = lic.`device_id`
                            LEFT JOIN `devices_limitations` dlim ON dlim.`device_id` = lic.`device_id`
                            WHERE
                                lic.`user_id` = {$userId} AND
                                lic.`product_type` = 'package'";

        if ($params['active']) {
            $fromWhere .= " AND lic.`status` = 'active'";
        }

        $query = "{$select} {$fromWhere}" . " LIMIT {$params['start']}, {$params['length']}";

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

}
