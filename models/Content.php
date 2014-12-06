<?php

namespace Models;

class Content extends \System\Model {

    public function getTemplatePath($template) {
        return 'content/' . $this->di->getTranslator()->getLocale() . '/' . $template;
    }
    
}
