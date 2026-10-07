<?php

namespace Tests\Unit\Enums;

use App\Enums\UserStatus;
use PHPUnit\Framework\TestCase;

class UserStatusTest extends TestCase
{
    public function test_stored_values_are_stable(): void
    {
        $this->assertSame(
            ['pending', 'active', 'suspended', 'blocked', 'inactive'],
            UserStatus::values(),
        );
    }

    public function test_only_active_is_fully_active(): void
    {
        $this->assertTrue(UserStatus::ACTIVE->isFullyActive());

        foreach ([UserStatus::PENDING, UserStatus::SUSPENDED, UserStatus::BLOCKED, UserStatus::INACTIVE] as $status) {
            $this->assertFalse($status->isFullyActive(), "{$status->value} must not be fully active");
        }
    }

    public function test_active_and_pending_may_authenticate(): void
    {
        $this->assertTrue(UserStatus::ACTIVE->canAuthenticate());
        $this->assertTrue(UserStatus::PENDING->canAuthenticate());
    }

    public function test_administrative_states_never_authenticate(): void
    {
        foreach ([UserStatus::SUSPENDED, UserStatus::BLOCKED, UserStatus::INACTIVE] as $status) {
            $this->assertFalse($status->canAuthenticate(), "{$status->value} must not authenticate");
        }
    }

    public function test_pending_can_authenticate_but_is_not_fully_active(): void
    {
        // The whole point of splitting the two concepts: a pending vendor may
        // sign in to see its status, but cannot reach the approved surface.
        $this->assertTrue(UserStatus::PENDING->canAuthenticate());
        $this->assertFalse(UserStatus::PENDING->isFullyActive());
    }
}
