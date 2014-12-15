<?php

namespace Models;

use Models\Support\SupportEmptyFieldException,
    Models\Support\SupportInvalidEmailException,
    Models\Support\SupportInvalidTypeException;

class Billing extends \System\Model
{

    public function getDataTableData($user, $params = array())
    {
        $userId = $this->getDb()->quote($user);

        $select = "SELECT lic.`id`, p.`name`, lic.`amount`, lic.`currency`,
            lic.`activation_date`, lic.`expiration_date`, lic.`status`,
            d.`name` `device`,
            IF(dlim.`id` IS NULL, lim.`sms`, dlim.`sms`) `sms`,
            IF(dlim.`id` IS NULL, lim.`call`, dlim.`call`) `call`";

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

    public function getLicenseDeviceInfo($licId)
    {
        $licId = (int)$licId;
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
