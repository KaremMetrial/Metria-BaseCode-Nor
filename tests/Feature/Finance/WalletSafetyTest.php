<?php
namespace Tests\Feature\Finance;
use App\Models\{User,Wallet,WalletTransaction};
use App\Services\Wallet\WalletService;
use App\Enums\{WalletTransactionType,ErrorCode};
use App\Exceptions\DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class WalletSafetyTest extends TestCase {
 use RefreshDatabase;
 public function test_credit_debit_replay_and_insufficient_balance_preserve_ledger(): void {
  $service=app(WalletService::class);$wallet=$service->forUser(User::factory()->create(),'EGP');
  $first=$service->change($wallet,WalletTransactionType::CREDIT,100,'credit','opening');
  $this->assertSame($first->id,$service->change($wallet,WalletTransactionType::CREDIT,100,'credit','opening')->id);
  $service->change($wallet,WalletTransactionType::DEBIT,80,'debit','purchase');
  try {$service->change($wallet,WalletTransactionType::DEBIT,80,'second','purchase');$this->fail('Expected insufficient balance');} catch(DomainException $e) {$this->assertSame(ErrorCode::INSUFFICIENT_BALANCE,$e->errorCode());}
  $this->assertSame(20,$wallet->fresh()->balance);$this->assertDatabaseCount('wallet_transactions',2);
 }
 public function test_same_key_different_payload_is_rejected(): void {
  $service=app(WalletService::class);$wallet=$service->forUser(User::factory()->create(),'EGP');$service->change($wallet,WalletTransactionType::CREDIT,100,'same','opening');
  try {$service->change($wallet,WalletTransactionType::CREDIT,101,'same','opening');$this->fail('Expected conflict');} catch(DomainException $e) {$this->assertSame(ErrorCode::IDEMPOTENCY_CONFLICT,$e->errorCode());}
  $this->assertSame(100,$wallet->fresh()->balance);$this->assertDatabaseCount('wallet_transactions',1);
 }
 public function test_zero_negative_and_excessive_amounts_do_not_change_balance(): void {
  $service=app(WalletService::class);$wallet=$service->forUser(User::factory()->create(),'EGP');
  foreach([0,-1,100000000,PHP_INT_MAX] as $amount) {try {$service->change($wallet,WalletTransactionType::CREDIT,$amount,'bad','bad');$this->fail('Expected invalid amount');}catch(DomainException $e){$this->assertSame(ErrorCode::INVALID_AMOUNT,$e->errorCode());}}
  $this->assertSame(0,$wallet->fresh()->balance);$this->assertDatabaseCount('wallet_transactions',0);
 }
 public function test_wallet_history_is_owner_scoped_and_admin_needs_permission(): void {
  $user=User::factory()->create();$other=User::factory()->create();$wallet=app(WalletService::class)->forUser($other,'EGP');Sanctum::actingAs($user);
  $this->getJson('/api/v1/client/wallet/'.$wallet->id.'/transactions')->assertNotFound();
  Sanctum::actingAs(User::factory()->admin()->create());
  $this->postJson('/api/v1/admin/wallets/'.$wallet->id.'/adjustments',['amount'=>1,'direction'=>'credit','reason'=>'test'],['Idempotency-Key'=>'test'])->assertForbidden();
  $this->assertSame(0,$wallet->fresh()->balance);
 }
 public function test_locked_wallet_rejects_mutation(): void {
  $service=app(WalletService::class);$wallet=$service->forUser(User::factory()->create(),'EGP');$wallet->forceFill(['is_locked'=>true])->save();
  $this->expectException(DomainException::class);$service->change($wallet,WalletTransactionType::CREDIT,100,'locked','test');
 }
}
