<?php

namespace Tests\Feature\Security;

use App\Enums\ErrorCode;
use App\Enums\WalletTransactionType;
use App\Exceptions\DomainException;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\User;
use App\Services\Audit\RedactSecrets;
use App\Services\Wallet\WalletService;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_bypass_requires_active_admin_identity(): void
    {
        $this->seed(RbacSeeder::class);
        foreach ([User::factory()->client()->create(), User::factory()->admin()->blocked()->create()] as $user) {
            $user->assignRole('super-admin');
            $this->assertFalse(Gate::forUser($user)->allows('unregistered.operation'));
        }
    }

    public function test_unhandled_api_error_never_exposes_internal_message_even_in_debug(): void
    {
        Route::middleware('api')->get('/api/_test/failure', fn () => throw new \RuntimeException('secret filesystem path'));
        config(['app.debug' => true]);
        $this->getJson('/api/_test/failure', ['X-Locale' => 'ar'])->assertStatus(500)->assertExactJson(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'حدث خطأ غير متوقع، يرجى المحاولة لاحقًا.', 'errors' => []]);
    }

    public function test_secret_redaction_handles_camel_case_headers_and_nested_objects(): void
    {
        $redacted = RedactSecrets::clean(['Authorization' => 'bearer secret', 'clientSecret' => 'secret', 'nested' => ['api-key' => 'secret'], 'object' => (object) ['token' => 'secret'], 'amount' => 120]);
        $this->assertSame('[REDACTED]', $redacted['Authorization']);
        $this->assertSame('[REDACTED]', $redacted['clientSecret']);
        $this->assertSame('[REDACTED]', $redacted['nested']['api-key']);
        $this->assertSame('[REDACTED_OBJECT]', $redacted['object']);
        $this->assertSame(120, $redacted['amount']);
    }

    public function test_financial_constraints_and_ledger_immutability_are_database_enforced(): void
    {
        $service = app(WalletService::class);
        $wallet = $service->forUser(User::factory()->create(), 'EGP');
        $entry = $service->change($wallet, WalletTransactionType::CREDIT, 100, 'initial', 'test');
        foreach ([fn () => DB::table('wallets')->where('id', $wallet->id)->update(['balance' => -1]), fn () => DB::table('wallet_transactions')->where('id', $entry->id)->update(['amount' => 200]), fn () => DB::table('wallet_transactions')->where('id', $entry->id)->delete()] as $mutate) {
            try {
                DB::transaction($mutate);
                $this->fail('Expected database constraint');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(100, $wallet->fresh()->balance);
        $this->assertSame(100, $entry->fresh()->amount);
    }

    public function test_location_parent_cannot_cascade_delete_children(): void
    {
        $parent = Country::factory()->create();
        $child = Governorate::factory()->forCountry($parent)->create();
        try {
            $parent->delete();
            $this->fail('Expected FK restriction');
        } catch (QueryException) {
            $this->assertDatabaseHas('governorates', ['id' => $child->id]);
        }
    }

    public function test_realtime_token_is_signed_scoped_and_short_lived(): void
    {
        config(['realtime.secret' => str_repeat('t', 32)]);
        $user = User::factory()->vendor()->create();
        Sanctum::actingAs($user);
        $token = $this->postJson('/api/v1/vendor/realtime/token')->assertOk()->json('data.token');
        [$payload, $sig] = explode('.', $token);
        $this->assertSame(hash_hmac('sha256', $payload, str_repeat('t', 32)), $sig);
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $this->assertSame((string) $user->id, $claims['sub']);
        $this->assertSame(120, $claims['exp'] - $claims['iat']);
        $this->getJson('/api/v1/client/profile')->assertForbidden();
    }

    public function test_wallet_and_payment_errors_have_both_locales(): void
    {
        foreach (['en', 'ar'] as $locale) {
            foreach ([ErrorCode::INSUFFICIENT_BALANCE, ErrorCode::INVALID_PAYMENT_STATUS, ErrorCode::INVALID_OTP, ErrorCode::INVALID_PHONE_NUMBER, ErrorCode::FORBIDDEN] as $code) {
                Route::middleware('api')->get('/api/_test/error-'.$code->value, fn () => throw new DomainException($code));
                $this->getJson('/api/_test/error-'.$code->value, ['X-Locale' => $locale])->assertJsonPath('message', trans('errors.'.$code->value, [], $locale))->assertJsonPath('code', $code->value);
            }
        }
    }
}
