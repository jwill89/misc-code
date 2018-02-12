<?php

namespace Exceptions;

// Exception Class for Object Errors
class ObjectException extends \Exception
{
    public function __construct($message, $code = 0, \Exception $previous = null) {

        // Ensure everything is assigned properly.
        parent::__construct($message, $code, $previous);

    }
}
