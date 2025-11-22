<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;

class Validator
{
    /**
     * @throws ValidationException
     */
    public function validate(array $data, array $rules): void
    {
        try {
            $validator = v::create();
            foreach ($rules as $field => $rule) {
                $validator->key($field, $rule);
            }
            $validator->assert($data);
        } catch (NestedValidationException $exception) {
        // Get all messages from the exception
            $messages = $exception->getMessages();
        // We'll just use the first message for simplicity, but you could concatenate them.
            $firstMessage = reset($messages);
            throw new ValidationException($firstMessage);
        }
    }
}
