<?php

namespace App\Exceptions\Authentication;

use App\Enums\ErrorCode;
use App\Enums\UserStatus;
use App\Exceptions\AppException;

/**
 * Raised when an account exists and the credentials are correct, but the status
 * forbids authentication outright (suspended, blocked, inactive).
 */
final class AccountDisabledException extends AppException
{
    public static function make(UserStatus $status): self
    {
        return new self("Account may not authenticate: status is '{$status->value}'.");
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::ACCOUNT_DISABLED;
    }
}
