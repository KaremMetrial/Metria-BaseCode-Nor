<?php

namespace Tests\Fixtures;

use App\Contracts\Sms\SmsProviderInterface;

final class RecordingSms implements SmsProviderInterface
{
    public static array $messages = [];

    public function __construct(array $settings = []) {}

    public function send(string $e164, string $message): void
    {
        self::$messages[] = ['phone' => $e164, 'message' => $message];
    }

    public static function code(): string
    {
        preg_match('/[0-9]{6}/', self::$messages[array_key_last(self::$messages)]['message'], $matches);

        return $matches[0];
    }
}
