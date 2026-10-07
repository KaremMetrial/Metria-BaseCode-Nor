<?php

namespace App\Exceptions\Authentication;

use App\Enums\ErrorCode;
use App\Exceptions\AppException;

/**
 * Raised by the `active` middleware when a pending account reaches a surface
 * that requires an approved account.
 *
 * Separate from AccountDisabledException because "under review" is actionable
 * and temporary, whereas "disabled" is an administrative decision. Clients need
 * to tell them apart to render the right screen.
 */
final class AccountPendingException extends AppException
{
    public static function make(): self
    {
        return new self('Account is pending review and may not reach this surface.');
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::ACCOUNT_PENDING;
    }
}
