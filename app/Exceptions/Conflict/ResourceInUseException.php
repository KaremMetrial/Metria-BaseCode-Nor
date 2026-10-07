<?php

namespace App\Exceptions\Conflict;

use App\Enums\ErrorCode;
use App\Exceptions\AppException;

/**
 * Raised when a delete is refused because other records still point at the row.
 *
 * 409, not 403: the caller *is* allowed to delete, the current state of the data
 * is what forbids it. Answering 403 would send clients hunting for a permission
 * they already hold, and re-trying the same request would never help.
 *
 * The reason is captured for logs only; the client gets the generic localized
 * message for RESOURCE_IN_USE, which avoids leaking which rows exist.
 */
final class ResourceInUseException extends AppException
{
    public static function make(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::RESOURCE_IN_USE;
    }
}
