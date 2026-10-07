<?php

namespace App\Support\Access;

use ReflectionClass;

/**
 * The canonical list of permissions.
 *
 * Permissions are `resource.action` strings. They are seeded from here and
 * referenced from here, so a permission cannot exist in a seeder but be
 * misspelled at the only place that checks it.
 */
final class PermissionRegistry
{
    // Users
    public const USERS_READ = 'users.read';

    public const USERS_CREATE = 'users.create';

    public const USERS_UPDATE = 'users.update';

    public const USERS_DELETE = 'users.delete';

    public const USERS_BLOCK = 'users.block';

    // Vendors
    public const VENDORS_READ = 'vendors.read';

    public const VENDORS_APPROVE = 'vendors.approve';

    public const VENDORS_REJECT = 'vendors.reject';

    // Locations
    public const LOCATIONS_READ = 'locations.read';

    public const LOCATIONS_MANAGE = 'locations.manage';

    // Categories
    public const CATEGORIES_READ = 'categories.read';

    public const CATEGORIES_CREATE = 'categories.create';

    public const CATEGORIES_UPDATE = 'categories.update';

    public const CATEGORIES_DELETE = 'categories.delete';

    // Payments
    public const PAYMENTS_READ = 'payments.read';

    public const PAYMENTS_CREATE = 'payments.create';

    public const PAYMENTS_REFUND = 'payments.refund';

    // Wallets
    public const WALLETS_READ = 'wallets.read';

    public const WALLETS_CREDIT = 'wallets.credit';

    public const WALLETS_DEBIT = 'wallets.debit';

    public const WALLETS_ADJUST = 'wallets.adjust';

    // Notifications
    public const NOTIFICATIONS_SEND = 'notifications.send';

    /**
     * Every permission, discovered by reflection.
     *
     * Reflection rather than a hand-maintained array: a constant added above is
     * automatically seeded, so the two lists cannot drift apart.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $constants = (new ReflectionClass(self::class))->getConstants();

        return array_values(array_filter($constants, 'is_string'));
    }
}
