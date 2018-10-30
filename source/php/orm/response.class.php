<?php

namespace System;

class Response
{

    /** @var bool */
    public $success;

    /** @var int */
    public $last_id;

    /** @var string */
    public $errorInfo;

    public function __construct(bool $success, string $errorInfo = null, $last_id = null)
    {
        $this->success = $success;
        $this->errorInfo = $errorInfo;
        $this->last_id = $last_id;
    }

}
