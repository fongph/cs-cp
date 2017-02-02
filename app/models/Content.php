<?php

namespace Models;

class Content extends \System\Model {

    public function getTemplatePath($template) {
        return 'content/' . $this->di->getTranslator()->getLocale() . '/' . $template;
    }

    public function getLegalInfo($code)
    {
        $code = $this->getDb()->quote($code);
        return $this->getDb()->query("SELECT lv.text, lv.legal_version_id as id, lv.legal_id FROM legal_versions lv 
                                LEFT JOIN legal_types lt ON lv.legal_id = lt.legal_id
                                WHERE lv.`status` = 'active' AND lt.code = {$code}
                                ORDER BY lv.created_at
                                LIMIT 1")->fetch();
    }

    public function saveUserAcceptance($userId, $legalId, $legalVersionId, $name)
    {
        $legalVersionId = (int) $legalVersionId;
        $legalId = (int) $legalId;
        $userId = (int) $userId;
        $option = $this->getDb()->quote('confirm-' . $name  . '-version-%');

        $this->getDb()->exec("INSERT INTO users_acceptance SET user_id = {$userId}, legal_id = {$legalId}, legal_version_id = {$legalVersionId};");

       return $this->getDb()->exec("UPDATE users_options SET `value` = 1 WHERE `user_id` = {$userId} AND `option` LIKE  {$option};");

    }
}
