<?php

namespace App\Enums;

/**
 * The account category of a user.
 *
 * This is intentionally NOT a role: roles describe a business function
 * (finance-admin, support-agent) and permissions describe individual actions.
 * `UserType` answers only "which API surface does this account belong to?".
 *
 * Adding a new actor (courier, employee, support, provider, restaurant) is a
 * single enum case plus one route group guarded by EnsureUserType.
 */
enum UserType: string
{
    case ADMIN = 'admin';
    case VENDOR = 'vendor';
    case CLIENT = 'client';

    /**
     * Human readable label for API/UI output.
     */
    public function label(): string
    {
        return __("enums.user_type.{$this->value}");
    }

    /**
     * Whether this account type may reach the staff (admin) surface.
     *
     * Kept as a method so that "is this staff?" is answered in one place when
     * more staff types are introduced.
     */
    public function isStaff(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Account types that authenticate with email + password instead of OTP.
     */
    public function usesPasswordAuthentication(): bool
    {
        return $this->isStaff();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
