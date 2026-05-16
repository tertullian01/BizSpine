<?php

namespace App\Models;

#[\AllowDynamicProperties]
class EmailLog extends BaseModel
{
    protected static string $tableName = 'email_logs';

    public ?int $id = null;
    public ?string $recipient = null;
    public ?string $subject = null;
    public ?string $body = null;
    public ?string $status = null;
    public ?string $error_message = null;
    public ?string $sent_at = null;
}