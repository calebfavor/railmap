<?php

namespace Railroad\Railmap\Exceptions;

use Exception;

class EntityHasNoIdentifierException extends Exception
{
    public function __construct()
    {
        parent::__construct('Could not store entity in identity map because it does not have an id.', 8000);
    }
}