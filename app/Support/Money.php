<?php

namespace App\Support;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;

final class Money
{
    public static function precision(string $currency): int
    {
        return in_array($currency, ['KWD', 'BHD', 'OMR', 'JOD'], true) ? 3 : 2;
    }

    public static function decimal(int $amount, string $currency): string
    {
        $precision = self::precision($currency);
        $factor = 10 ** $precision;

        return sprintf('%d.%0'.$precision.'d', intdiv($amount, $factor), $amount % $factor);
    }

    public static function minor(mixed $amount, string $currency): int
    {
        if (! is_string($amount) && ! is_int($amount) && ! is_float($amount)) {
            throw new DomainException(ErrorCode::INVALID_AMOUNT);
        }
        $value = (string) $amount;
        if (! preg_match('/^(\d{1,9})(?:\.(\d{1,9}))?$/D', $value, $parts)) {
            throw new DomainException(ErrorCode::INVALID_AMOUNT);
        }
        $precision = self::precision($currency);
        $fraction = $parts[2] ?? '';
        if (trim(substr($fraction, $precision), '0') !== '') {
            throw new DomainException(ErrorCode::INVALID_AMOUNT);
        }

        return (int) $parts[1] * (10 ** $precision) + (int) str_pad(substr($fraction, 0, $precision), $precision, '0');
    }
}
