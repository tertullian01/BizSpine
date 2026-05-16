<?php

namespace App\Exceptions;

use Exception;

class ValidationException extends Exception
{
    private array $errors;
    public function __construct(array|string $errors, string $message = "Validation failed", int $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        if (is_string($errors)) {
            $this->errors = [$errors];
        } else {
            $this->errors = $errors;
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): string
    {
        return $this->errors[0] ?? '';
    }
}
