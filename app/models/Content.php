<?php

namespace Models;

class Content extends \System\Model {

    public function getTemplatePath($template) {
        return 'content/' . $this->di->getTranslator()->getLocale() . '/' . $template;
    }

    public function getLegalInfo($code)
    {
        $code = $this->getDb()->quote($code);
        return $this->getDb()->query("SELECT lv.text FROM legal_versions lv 
                                LEFT JOIN legal_types lt ON lv.legal_id = lt.legal_id
                                WHERE lv.`status` = 'active' AND lt.code = {$code}
                                ORDER BY lv.created_at
                                LIMIT 1")->fetchColumn();
    }
}
