<?php

namespace Tests\Feature;

use App\Enums\WalletTransactionType;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Page;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Referral;
use App\Models\SupportConversation;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\DemoFinanceSeeder;
use Database\Seeders\DemoIntegrationSeeder;
use Database\Seeders\DemoMediaSeeder;
use Database\Seeders\DemoNotificationSeeder;
use Database\Seeders\DemoReferralSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DemoSupportSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeding_builds_usable_features_without_external_work(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        config(['notifications.email_enabled' => true, 'notifications.fcm_enabled' => true]);

        $this->seed(DemoSeeder::class);

        $client = User::query()->where('phone', '+201000000101')->sole();
        $this->assertNull($client->email);
        $this->assertNull($client->password);
        $this->assertTrue($client->hasCompletedProfile());
        $this->assertFalse(User::query()->where('phone', '+201000000103')->sole()->hasCompletedProfile());
        $this->assertDatabaseCount('terms_acceptances', 3);
        $this->assertDatabaseCount('contact_messages', 3);
        $this->assertDatabaseCount('support_conversations', 4);
        $this->assertDatabaseCount('support_messages', 7);
        $this->assertDatabaseCount('referrals', 1);
        $this->assertDatabaseCount('share_links', 3);
        $this->assertSame($client->id, Referral::query()->sole()->referrer_id);
        $this->assertDatabaseCount('my_fatoorah_entities', 7);
        $this->assertDatabaseCount('my_fatoorah_operations', 7);
        $this->assertDatabaseCount('otp_challenges', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('device_tokens', 0);
        $this->assertFalse(DB::table('notification_deliveries')->where('status', 'pending')->exists());
        $this->assertFalse(UserNotification::query()->whereNull('published_at')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'page.published')->exists());

        $this->getJson('/api/v1/pages')->assertOk()->assertJsonCount(6, 'data');
        $this->getJson('/api/v1/categories?search=قهوه ابراهيم')->assertOk()->assertJsonCount(1, 'data.items');
        Sanctum::actingAs($client);
        $this->getJson('/api/v1/client/notifications')->assertOk()->assertJsonCount(2, 'data.items')->assertJsonPath('data.unread_count', 1);
        $this->getJson('/api/v1/client/support/conversations')->assertOk()->assertJsonPath('data.meta.total', 4);
        $media = Media::query()->where('purpose', 'support')->whereNotNull('support_message_id')->sole();
        $this->get('/api/v1/client/media/'.$media->id.'/download')->assertOk();
        foreach (Media::all() as $file) {
            Storage::disk('local')->assertExists($file->path);
            $this->assertSame($file->sha256, hash('sha256', Storage::disk('local')->get($file->path)));
            $this->assertNotFalse(imagecreatefromstring(Storage::disk('local')->get($file->path)));
        }
        $this->assertDatabaseCount('media', 6);
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_rerunning_preserves_balances_profiles_and_existing_user_changes(): void
    {
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $client = User::query()->where('phone', '+201000000101')->sole();
        $client->forceFill(['name' => 'Edited demo profile'])->save();
        $wallet = Wallet::query()->where('user_id', $client->id)->sole();
        $this->assertSame(127500, $wallet->balance);
        app(WalletService::class)->change($wallet, WalletTransactionType::DEBIT, 500, 'manual-demo-purchase', 'manual_purchase');
        $conversation = SupportConversation::query()->where('user_id', $client->id)->where('status', 'new')->sole();
        $conversation->forceFill(['status' => 'resolved'])->save();
        $tables = ['users', 'categories', 'pages', 'page_revisions', 'terms_acceptances', 'wallets', 'wallet_transactions', 'payments', 'payment_refunds', 'contact_messages', 'support_conversations', 'support_messages', 'user_notifications', 'notification_deliveries', 'referrals', 'share_links', 'media', 'audit_logs', 'my_fatoorah_entities', 'my_fatoorah_operations'];
        $counts = array_map(fn (string $table): int => DB::table($table)->count(), $tables);
        $version = $client->refresh()->notifications_version;

        $this->seed(DemoSeeder::class);

        $this->assertSame($counts, array_map(fn (string $table): int => DB::table($table)->count(), $tables));
        $this->assertSame(127000, $wallet->refresh()->balance);
        $this->assertSame('Edited demo profile', $client->refresh()->name);
        $this->assertSame($version, $client->notifications_version);
        $this->assertSame('resolved', $conversation->refresh()->status);
        foreach (Wallet::all() as $item) {
            $credits = WalletTransaction::query()->where('wallet_id', $item->id)->where('direction', 'credit')->sum('amount');
            $debits = WalletTransaction::query()->where('wallet_id', $item->id)->where('direction', 'debit')->sum('amount');
            $this->assertSame($item->balance, (int) $credits - (int) $debits);
        }
        foreach (Payment::all() as $payment) {
            $this->assertSame($payment->refunded_amount, (int) PaymentRefund::query()->where('payment_id', $payment->id)->where('status', 'succeeded')->sum('amount'));
        }
    }

    public function test_demo_content_does_not_publish_or_overwrite_existing_editor_content(): void
    {
        $this->seed(DatabaseSeeder::class);
        $page = Page::query()->where('slug', 'terms')->sole();
        $page->translate('en')->content = 'Existing terms draft';
        $page->save();

        $this->seed(DemoContentSeeder::class);

        $this->assertNull($page->refresh()->published_revision_id);
        $this->assertSame('Existing terms draft', $page->translate('en')->content);
    }

    #[DataProvider('demoSeeders')]
    public function test_demo_seeders_refuse_production_even_when_invoked_individually(string $seeder): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Demo seeders are only available');

        $this->artisan('db:seed', ['--class' => $seeder, '--force' => true, '--no-interaction' => true])->run();
    }

    public static function demoSeeders(): array
    {
        return array_map(fn (string $class): array => [$class], [DemoSeeder::class, DemoUserSeeder::class, DemoContentSeeder::class,
            DemoFinanceSeeder::class, DemoSupportSeeder::class, DemoNotificationSeeder::class, DemoReferralSeeder::class,
            DemoMediaSeeder::class, DemoIntegrationSeeder::class]);
    }
}
