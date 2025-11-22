<?php

namespace App\Exceptions;

use Exception;

class ValidationException extends Exception
{
    // Custom exception for validation errors, defaults to 400
    protected $code = 400;
}
