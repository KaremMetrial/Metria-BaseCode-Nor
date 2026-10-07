<?php

namespace App\Exceptions\Authentication;

use App\Enums\ErrorCode;
use App\Exceptions\AppException;

/**
 * Raised when a password does not match, *and* when the account does not exist.
 *
 * Both cases must produce an identical response; otherwise the endpoint becomes
 * an account-enumeration oracle. The factory deliberately takes no arguments so
 * a caller cannot accidentally pass the email (or a reason) into a message that
 * reaches the client.
 */
final class InvalidCredentialsException extends AppException
{
    public static function make(): self
    {
        return new self('Authentication failed: credentials did not match.');
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::INVALID_CREDENTIALS;
    }
}
