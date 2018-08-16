<?php

namespace FoneroRPC\Fonero\Exceptions;

use RuntimeException;

class FonerodException extends RuntimeException
{
    /**
     * Construct new fonerod exception.
     *
     * @param object $error
     *
     * @return void
     */
    public function __construct($error)
    {
        parent::__construct($error['message'], $error['code']);
    }
}
