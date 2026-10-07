<?php

namespace App\Exceptions\Phone;

use App\Enums\ErrorCode;
use App\Exceptions\AppException;
use Throwable;

/**
 * Raised when a phone number cannot be parsed or is not a valid number for the
 * given (or inferred) region.
 *
 * The raw number is never embedded in the message: exception messages are
 * logged, and a phone number is an authentication identifier, so it must be
 * masked rather than written to disk verbatim.
 */
final class InvalidPhoneNumberException extends AppException
{
    public static function make(?string $regionIso2 = null, ?Throwable $previous = null): self
    {
        return new self(
            $regionIso2 === null
                ? 'Phone number could not be parsed or is not valid.'
                : "Phone number could not be parsed or is not valid for region '{$regionIso2}'.",
            $previous,
        );
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::INVALID_PHONE_NUMBER;
    }

    public function errors(): array
    {
        return ['phone' => [$this->userMessage()]];
    }
}
