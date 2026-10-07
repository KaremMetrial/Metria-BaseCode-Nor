<?php

namespace App\Enums;

/**
 * The lifecycle state of a user account.
 *
 * Authentication and authorization are deliberately separated:
 *
 *  - `canAuthenticate()` decides whether credentials/OTP may be exchanged for
 *    a token at all.
 *  - `isFullyActive()` decides whether the account may reach the protected
 *    (non-onboarding) surface — enforced by the `active` middleware.
 *
 * A `pending` vendor can therefore sign in and see "your account is under
 * review" instead of receiving a misleading "invalid credentials" error, while
 * still being unable to touch anything that requires an approved account.
 */
enum UserStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case BLOCKED = 'blocked';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return __("enums.user_status.{$this->value}");
    }

    /**
     * Whether the account may exchange credentials for a token.
     *
     * Suspended, blocked and inactive accounts are administrative decisions and
     * must never authenticate.
     */
    public function canAuthenticate(): bool
    {
        return match ($this) {
            self::ACTIVE, self::PENDING => true,
            self::SUSPENDED, self::BLOCKED, self::INACTIVE => false,
        };
    }

    /**
     * Whether the account may reach the protected (non-onboarding) surface.
     */
    public function isFullyActive(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
