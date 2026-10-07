<?php

namespace Tests\Feature\Finance;

use App\Enums\OtpPurpose;
use App\Enums\UserType;
use App\Enums\WalletTransactionType;
use App\Models\Country;
use App\Models\Payment;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Fixtures\RecordingSms;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires MySQL and separate worker connections; run the documented integration command.');
        }
    }

    private function race(array $left, array $right): array
    {
        $barrier = sys_get_temp_dir().'/metrial-race-'.bin2hex(random_bytes(8));
        $cfg = config('database.connections.mysql');
        $env = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $cfg['host'], 'DB_PORT' => (string) $cfg['port'], 'DB_DATABASE' => $cfg['database'], 'DB_USERNAME' => $cfg['username'], 'DB_PASSWORD' => $cfg['password'], 'DB_URL' => '', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'stderr'];
        $processes = [];
        try {
            foreach ([$left, $right] as $index => $data) {
                $data += ['barrier' => $barrier, 'worker' => $index];
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/concurrent-worker.php'), base64_encode(json_encode($data, JSON_THROW_ON_ERROR))], base_path(), $env);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
            while (! is_file($barrier.'.0') || ! is_file($barrier.'.1')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Workers failed to reach barrier: '.implode(' ', array_map(fn ($p) => $p->getErrorOutput(), $processes)));
                }
                usleep(10000);
            }
            touch($barrier.'.go');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($processes as $p) {
                if ($p->isRunning()) {
                    $p->stop();
                }
            }
            foreach (glob($barrier.'.*') as $file) {
                unlink($file);
            }
        }
    }

    public function test_concurrent_debits_cannot_overdraw_wallet(): void
    {
        $service = app(WalletService::class);
        $wallet = $service->forUser(User::factory()->create(), 'EGP');
        $service->change($wallet, WalletTransactionType::CREDIT, 100, 'initial', 'funding');
        $result = $this->race(['operation' => 'debit', 'wallet' => $wallet->id, 'key' => 'one'], ['operation' => 'debit', 'wallet' => $wallet->id, 'key' => 'two']);
        $this->assertSame(1, count(array_filter($result, fn ($r) => $r['success'])));
        $this->assertSame(20, $wallet->fresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_concurrent_same_key_mutates_once(): void
    {
        $service = app(WalletService::class);
        $wallet = $service->forUser(User::factory()->create(), 'EGP');
        $service->change($wallet, WalletTransactionType::CREDIT, 100, 'initial', 'funding');
        $input = ['operation' => 'debit', 'wallet' => $wallet->id, 'key' => 'same'];
        $result = $this->race($input, $input);
        $this->assertSame([true, true], array_column($result, 'success'));
        $this->assertSame(20, $wallet->fresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_concurrent_otp_consumption_issues_one_token(): void
    {
        $country = Country::factory()->withIso2('EG')->create();
        config(['sms.default' => 'test', 'sms.providers.test' => ['driver' => RecordingSms::class]]);
        $id = app(OtpService::class)->issue('+201012345678', $country->id, UserType::CLIENT, OtpPurpose::LOGIN, null, 'en');
        $input = ['operation' => 'otp', 'input' => ['country_id' => $country->id, 'phone' => '01012345678', 'challenge_id' => $id, 'code' => RecordingSms::code()]];
        $result = $this->race($input, $input);
        $this->assertSame(1, count(array_filter($result, fn ($r) => $r['success'])));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_concurrent_webhooks_credit_once(): void
    {
        $user = User::factory()->create();
        $wallet = app(WalletService::class)->forUser($user, 'EGP');
        $payment = new Payment;
        $payment->forceFill(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'wallet_id' => $wallet->id, 'provider' => 'stripe', 'provider_reference' => 'pi_concurrent', 'amount' => 10000, 'currency' => 'EGP', 'status' => 'pending', 'refunded_amount' => 0, 'idempotency_key' => 'test', 'request_hash' => str_repeat('a', 64)])->save();
        $body = json_encode(['id' => 'evt_concurrent', 'type' => 'payment_intent.succeeded', 'livemode' => false, 'data' => ['object' => ['id' => 'pi_concurrent', 'amount' => 10000, 'amount_received' => 10000, 'currency' => 'egp', 'status' => 'succeeded', 'metadata' => ['payment_uuid' => $payment->uuid]]]], JSON_THROW_ON_ERROR);
        $time = now()->timestamp;
        $input = ['operation' => 'webhook', 'body' => $body, 'signature' => 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$body, 'concurrency-test')];
        $result = $this->race($input, $input);
        $this->assertSame([true, true], array_column($result, 'success'));
        $this->assertSame(10000, $wallet->fresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }

    public function test_concurrent_refunds_cannot_exceed_paid_amount(): void {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $actor=User::factory()->admin()->create();$actor->assignRole('finance-admin');
        $user=User::factory()->create();$wallet=app(WalletService::class)->forUser($user,'EGP');
        app(WalletService::class)->change($wallet,WalletTransactionType::CREDIT,100,'initial','funding');
        $payment=new Payment;
        $payment->forceFill(['uuid'=>(string)\Illuminate\Support\Str::uuid(),'user_id'=>$user->id,'wallet_id'=>$wallet->id,'provider'=>'stripe','provider_reference'=>'pi_refunds','amount'=>100,'currency'=>'EGP','status'=>'paid','refunded_amount'=>0,'idempotency_key'=>'initial','request_hash'=>str_repeat('a',64)])->save();
        $base=['operation'=>'refund','actor'=>$actor->id,'payment'=>$payment->id];
        $result=$this->race($base+['key'=>'first'],$base+['key'=>'second']);
        $this->assertSame(1,count(array_filter($result,fn($r)=>$r['success'])));
        $this->assertSame(80,$payment->fresh()->refunded_amount);$this->assertSame(20,$wallet->fresh()->balance);
        $this->assertDatabaseCount('payment_refunds',1);$this->assertDatabaseCount('wallet_transactions',2);
    }
}
