<?php

namespace Models\Cp\Locations;

/**
 * Description of LocationsException
 *
 * @author root
 */
class LocationsException extends \RuntimeException
{
    public function __construct($message, $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
