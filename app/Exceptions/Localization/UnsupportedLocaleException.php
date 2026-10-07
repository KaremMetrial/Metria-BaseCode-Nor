<?php

namespace App\Exceptions\Localization;

use App\Enums\ErrorCode;
use App\Exceptions\AppException;

/**
 * Raised only when a client *explicitly* requests an unsupported locale
 * (?locale=xx or the X-Locale header).
 *
 * A malformed Accept-Language header is negotiated silently instead of
 * rejected: browsers send it automatically, and answering every request with a
 * 422 because of a header the user never chose would be hostile.
 */
final class UnsupportedLocaleException extends AppException
{
    public static function make(string $locale): self
    {
        return new self("Unsupported locale requested: '{$locale}'.");
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::UNSUPPORTED_LOCALE;
    }

    public function errors(): array
    {
        return ['locale' => [$this->userMessage()]];
    }
}
