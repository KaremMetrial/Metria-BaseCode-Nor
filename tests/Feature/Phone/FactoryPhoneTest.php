<?php

namespace Tests\Feature\Phone;

use App\Contracts\Phone\PhoneNumberServiceInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the test fixtures themselves.
 *
 * A factory that produces invalid phone numbers makes every phone-dependent
 * test meaningless, and the failure then surfaces much later as a confusing
 * validation error elsewhere.
 */
class FactoryPhoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_phones_are_valid_unique_e164(): void
    {
        $users = User::factory()->count(10)->create();
        $service = app(PhoneNumberServiceInterface::class);

        $this->assertSame(10, $users->pluck('phone')->unique()->count(), 'Factory phones must be unique');

        foreach ($users as $user) {
            $this->assertStringStartsWith('+', $user->phone, 'Factory phone must be in E.164 form');
            $this->assertTrue($service->isValid($user->phone), "Factory phone [{$user->phone}] is not valid");

            $parsed = $service->parse($user->phone);

            $this->assertSame($user->phone, $parsed->e164, 'Factory phone must already be canonical');
            $this->assertTrue($parsed->canReceiveSms(), 'Factory phones must be SMS-capable');
        }
    }

    public function test_the_default_state_is_a_verified_phone(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->phone);
        $this->assertTrue($user->hasVerifiedPhone());
    }

    public function test_without_phone_removes_the_number_and_its_verification(): void
    {
        $user = User::factory()->withoutPhone()->create();

        $this->assertNull($user->phone);
        $this->assertNull($user->phone_verified_at);
        $this->assertFalse($user->hasVerifiedPhone());
    }

    public function test_with_unverified_phone_keeps_the_number_but_clears_verification(): void
    {
        $user = User::factory()->withUnverifiedPhone()->create();

        $this->assertNotNull($user->phone);
        $this->assertNull($user->phone_verified_at);
    }

    public function test_without_password_produces_a_phone_only_account(): void
    {
        $this->assertNull(User::factory()->withoutPassword()->create()->password);
    }
}
