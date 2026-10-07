<?php

namespace App\Contracts\Sms;

interface SmsProviderInterface
{
    public function send(string $e164, #[\SensitiveParameter] string $message): void;
}
