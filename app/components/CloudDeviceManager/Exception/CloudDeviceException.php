<?php

namespace Components\CloudDeviceManager\Exception;

/**
 * Description of BasicException
 *
 * @author orest
 */
class CloudDeviceException extends \RuntimeException {

    public function __construct($message, $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

}
