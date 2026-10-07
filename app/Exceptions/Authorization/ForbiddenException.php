<?php

namespace App\Exceptions\Authorization;

use App\Enums\ErrorCode;
use App\Exceptions\AppException;

/**
 * Raised when an authenticated user fails an authorization check.
 *
 * The reason is captured for logs only. Telling a client *why* it was denied
 * ("you may not access user 41") leaks the existence of resources it has no
 * business knowing about, so `userMessage()` stays generic.
 */
final class ForbiddenException extends AppException
{
    public static function make(?string $reason = null): self
    {
        return new self($reason ?? 'Action denied by an authorization check.');
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::FORBIDDEN;
    }
}
