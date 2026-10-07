<?php

namespace App\Exceptions\Phone;

use App\Enums\ErrorCode;
use App\Exceptions\AppException;

/**
 * Raised when a number parses successfully but belongs to a different country
 * than the one the client selected.
 *
 * This is the guard that stops "+20 Egypt" being paired with "SA" (or a client
 * silently spoofing the country to dodge regional rules).
 */
final class PhoneCountryMismatchException extends AppException
{
    public static function make(string $expectedRegionIso2): self
    {
        return new self("Phone number does not belong to the expected region '{$expectedRegionIso2}'.");
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::PHONE_COUNTRY_MISMATCH;
    }

    public function errors(): array
    {
        return ['phone' => [$this->userMessage()]];
    }
}
