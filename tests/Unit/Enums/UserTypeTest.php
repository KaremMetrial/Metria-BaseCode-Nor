<?php

namespace Tests\Unit\Enums;

use App\Enums\UserType;
use PHPUnit\Framework\TestCase;

class UserTypeTest extends TestCase
{
    public function test_stored_values_are_stable(): void
    {
        // These strings are persisted in users.type, so changing them is a
        // data migration, not a refactor.
        $this->assertSame(['admin', 'vendor', 'client'], UserType::values());
    }

    public function test_only_admin_is_staff(): void
    {
        $this->assertTrue(UserType::ADMIN->isStaff());

        $this->assertFalse(UserType::VENDOR->isStaff());
        $this->assertFalse(UserType::CLIENT->isStaff());
    }

    public function test_only_staff_authenticates_with_a_password(): void
    {
        $this->assertTrue(UserType::ADMIN->usesPasswordAuthentication());

        $this->assertFalse(UserType::VENDOR->usesPasswordAuthentication());
        $this->assertFalse(UserType::CLIENT->usesPasswordAuthentication());
    }

    public function test_unknown_value_does_not_resolve(): void
    {
        $this->assertNull(UserType::tryFrom('courier'), 'courier is not implemented yet and must not silently resolve');
    }
}
