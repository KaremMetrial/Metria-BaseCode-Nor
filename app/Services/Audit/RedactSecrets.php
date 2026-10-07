<?php

namespace App\Services\Audit;

final class RedactSecrets
{
    public static function clean(array $values): array
    {
        foreach ($values as $key => $value) {
            $normalized = is_string($key) ? strtolower(preg_replace('/[^a-z0-9]/i', '', $key)) : '';
            if (preg_match('/password|token|secret|authorization|cookie|apikey|privatekey|otp|cardnumber|cvv|cvc|phone|email/', $normalized)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = self::clean($value);
            } elseif (is_object($value)) {
                $values[$key] = '[REDACTED_OBJECT]';
            }
        }

        return $values;
    }
}
