<?php

namespace App\Exceptions;

use RuntimeException;

class InUseException extends RuntimeException
{
    public function __construct(string $model, string $identifier)
    {
        parent::__construct("Cannot modify \"{$identifier}\": it is referenced by existing records.");
    }
}
