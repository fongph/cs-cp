<?php

namespace Models\Cp;

class BaseModel extends \System\Model
{

    public function getDb()
    {
        return $this->di->get('dataDb');
    }

}
