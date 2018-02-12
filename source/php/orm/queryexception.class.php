<?php

namespace Exceptions;

// Specific Exception Class to throw for DB Query Errors
class QueryException extends \Exception
{
    public function __construct($message, $code = 0, \Exception $previous = null) {

        // Ensure everything is assigned properly.
        parent::__construct($message, $code, $previous);

    }
}
