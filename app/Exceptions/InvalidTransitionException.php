<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidTransitionException extends RuntimeException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct("Cannot transition from '{$from}' to '{$to}'.");
    }
}
