<?php

namespace App\Exceptions\Phone;

use App\Enums\ErrorCode;
use App\Exceptions\AppException;

/**
 * Raised when the requested region is not a known phone-numbering region at all
 * (e.g. "ZZ"), which is distinct from a country that merely is not enabled for
 * the product. The latter is a business decision handled in Phase 2 against
 * `countries.is_active`, not a parsing failure.
 */
final class UnsupportedPhoneCountryException extends AppException
{
    public static function make(string $regionIso2): self
    {
        return new self("Region '{$regionIso2}' is not a known phone-numbering region.");
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::UNSUPPORTED_PHONE_COUNTRY;
    }

    public function errors(): array
    {
        return ['country' => [$this->userMessage()]];
    }
}
