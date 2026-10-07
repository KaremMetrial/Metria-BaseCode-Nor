<?php

namespace App\Support\Access;

/**
 * The default roles and the permissions they grant.
 *
 * Note what is *not* here: no role is named after an actor. `UserType` already
 * answers "is this an admin?", so roles answer only "which business function
 * does this administrator perform?". A `client` role would duplicate UserType
 * and eventually contradict it.
 */
final class RoleRegistry
{
    /**
     * Fully privileged role.
     *
     * Deliberately granted no permissions: it is allowed everything through the
     * `Gate::before` bypass, so its authority cannot drift when a new permission
     * is added. Assigning permissions explicitly would mean every new permission
     * silently works for finance-admin until someone remembers to grant it.
     */
    public const SUPER_ADMIN = 'super-admin';

    public const FINANCE_ADMIN = 'finance-admin';

    public const SUPPORT_AGENT = 'support-agent';

    /**
     * Role => permissions.
     *
     * @return array<string, list<string>>
     */
    public static function roles(): array
    {
        return [
            self::FINANCE_ADMIN => [
                PermissionRegistry::PAYMENTS_READ,
                PermissionRegistry::PAYMENTS_REFUND,
                PermissionRegistry::WALLETS_READ,
            ],

            self::SUPPORT_AGENT => [
                PermissionRegistry::USERS_READ,
                PermissionRegistry::USERS_BLOCK,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::SUPER_ADMIN, ...array_keys(self::roles())];
    }
}
