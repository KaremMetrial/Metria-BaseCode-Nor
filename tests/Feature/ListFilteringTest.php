<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\ContactMessage;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\MyFatoorahEntity;
use App\Models\MyFatoorahOperation;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Payment;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\SupportConversation;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Notifications\NotificationOutbox;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ListFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_combine_filters_and_sort_type_with_stable_pagination(): void
    {
        $this->admin();
        $first = User::factory()->client()->create(['name' => 'Same', 'created_at' => '2026-10-11 00:00:00']);
        $second = User::factory()->client()->blocked()->create(['name' => 'Same', 'created_at' => '2026-10-11 23:59:59']);
        User::factory()->vendor()->create(['name' => 'Same']);
        User::factory()->client()->create(['name' => 'Same', 'created_at' => '2026-10-12 00:00:00']);
        $query = 'type=client&status[]=active&status[]=blocked&search=Same&created_from=2026-10-11&created_to=2026-10-11&sort=name&sort_type=desc&per_page=1';
        $this->getJson('/api/v1/admin/users?'.$query)->assertOk()->assertJsonPath('data.items.0.id', $second->id)->assertJsonPath('data.meta.total', 2)->assertJsonPath('data.meta.per_page', 1);
        $this->getJson('/api/v1/admin/users?'.$query.'&page=2')->assertOk()->assertJsonPath('data.items.0.id', $first->id);
    }

    public function test_nullable_sorts_put_missing_values_last_and_false_filters_are_applied(): void
    {
        $this->admin();
        $missing = User::factory()->client()->withUnverifiedPhone()->create(['last_login_at' => null]);
        $recent = User::factory()->client()->withUnverifiedPhone()->create(['last_login_at' => '2026-10-11 10:00:00']);
        User::factory()->client()->create(['last_login_at' => '2026-10-12 10:00:00']);
        $this->getJson('/api/v1/admin/users?type=client&phone_verified=0&sort=-last_login_at')->assertOk()->assertJsonPath('data.items.0.id', $recent->id)->assertJsonPath('data.items.1.id', $missing->id)->assertJsonCount(2, 'data.items');
    }

    #[TestWith(['sort=password', 'sort'])]
    #[TestWith(['sort=id%20desc', 'sort'])]
    #[TestWith(['sort=id,-id', 'sort'])]
    #[TestWith(['sort=id,name,created_at,last_login_at', 'sort'])]
    #[TestWith(['sort=-id&sort_type=asc', 'sort_type'])]
    #[TestWith(['sort=id,name&sort_type=asc', 'sort_type'])]
    #[TestWith(['sort_type=desc', 'sort_type'])]
    #[TestWith(['sort=id&sort_type=sideways', 'sort_type'])]
    #[TestWith(['sort[]=id', 'sort'])]
    #[TestWith(['per_page=101', 'per_page'])]
    #[TestWith(['page=0', 'page'])]
    #[TestWith(['created_from=2026-10-12&created_to=2026-10-11', 'created_to'])]
    #[TestWith(['created_from=2026-02-30', 'created_from'])]
    #[TestWith(['status[]=active&status[]=bogus', 'status.1'])]
    #[TestWith(['status[]=active&status[]=active', 'status.0'])]
    #[TestWith(['status[hidden]=active', 'status'])]
    #[TestWith(['filter[password]=secret', 'filter'])]
    public function test_invalid_list_parameters_return_field_errors(string $query, string $field): void
    {
        $this->admin();
        $response = $this->getJson('/api/v1/admin/users?'.$query)->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertNotEmpty($response->json('errors')[$field] ?? null);
    }

    public function test_filters_never_grant_admin_access(): void
    {
        Sanctum::actingAs(User::factory()->client()->create());
        $this->getJson('/api/v1/admin/users?type=client&sort=id')->assertForbidden();
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/v1/admin/users?type=client')->assertForbidden();
    }

    #[TestWith(['client'])]
    #[TestWith(['vendor'])]
    public function test_payment_filters_keep_actor_ownership_and_require_currency(string $actor): void
    {
        $user = User::factory()->create(['type' => $actor]);
        $wanted = $this->payment($user, 1500);
        $this->payment($user, 500);
        $this->payment(User::factory()->create(), 2000);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/'.$actor.'/payments?currency=EGP&status[]=paid&status[]=pending&amount_min=1000&amount_max=2000&sort=-amount')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $wanted->id);
        $this->getJson('/api/v1/'.$actor.'/payments?sort=amount')->assertUnprocessable()->assertJsonPath('errors.currency.0', __('filters.currency'));
        $this->getJson('/api/v1/'.$actor.'/payments?currency=EGP&amount_min=2000&amount_max=1000')->assertUnprocessable();
        $this->getJson('/api/v1/'.$actor.'/payments?user_id='.$user->id)->assertUnprocessable()->assertJsonPath('errors.user_id.0', __('filters.unsupported'));
    }

    public function test_admin_finance_filters_by_owner_currency_and_lock_state(): void
    {
        $this->admin();
        $user = User::factory()->create();
        $payment = $this->payment($user, 900);
        $this->payment(User::factory()->create(), 1200);
        $this->getJson('/api/v1/admin/payments?user_id='.$user->id.'&provider=stripe&currency=EGP&sort=amount')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $payment->id);
        $this->getJson('/api/v1/admin/wallets?user_id='.$user->id.'&currency=EGP&is_locked=0&balance_min=0&balance_max=0&sort=-balance')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $payment->wallet_id);
        $this->getJson('/api/v1/admin/wallets?sort=balance')->assertUnprocessable();
    }

    public function test_wallets_keep_the_array_contract_and_transactions_are_wallet_scoped(): void
    {
        $user = User::factory()->create();
        $payment = $this->payment($user, 900);
        $other = $this->payment(User::factory()->create(), 100);
        $first = $this->transaction($payment->wallet_id, 'credit', 300);
        $this->transaction($payment->wallet_id, 'debit', 200);
        $this->transaction($other->wallet_id, 'credit', 300);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/client/wallet?currency=EGP&sort=id')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $payment->wallet_id);
        $this->getJson('/api/v1/client/wallet/'.$payment->wallet_id.'/transactions?direction=credit&reason=test&amount_min=300&sort=-amount')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $first->id);
        $this->getJson('/api/v1/client/wallet/'.$other->wallet_id.'/transactions?sort=id')->assertNotFound();
        $this->getJson('/api/v1/client/wallet?per_page=10')->assertUnprocessable();
    }

    public function test_localized_category_search_sort_fallback_and_hidden_ancestors(): void
    {
        $root = Category::create(['en' => ['name' => 'Alpha'], 'ar' => ['name' => 'ألف'], 'is_active' => true]);
        $fallback = Category::create(['en' => ['name' => 'Beta'], 'is_active' => true]);
        $hidden = Category::create(['en' => ['name' => 'Hidden'], 'is_active' => false]);
        Category::create(['parent_id' => $hidden->id, 'en' => ['name' => 'Beta hidden child'], 'is_active' => true]);
        $this->getJson('/api/v1/categories?locale=ar&search=Beta&sort=name&root_only=1')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $fallback->id);
        $this->getJson('/api/v1/categories?locale=ar&search=ألف')->assertOk()->assertJsonPath('data.items.0.id', $root->id);
        $this->getJson('/api/v1/categories?locale=en&sort=-name')->assertOk()->assertJsonPath('data.items.0.id', $fallback->id)->assertJsonPath('data.meta.total', 2);
        $this->getJson('/api/v1/categories?keyword=Beta&search=Other')->assertUnprocessable();
        $this->getJson('/api/v1/categories?root_only=1&parent_id='.$root->id)->assertUnprocessable();
        $this->getJson('/api/v1/categories?is_active=0')->assertUnprocessable();
        $this->admin();
        $this->getJson('/api/v1/admin/categories?is_active=0&search=Hidden')->assertOk()->assertJsonPath('data.items.0.id', $hidden->id);
    }

    public function test_location_lists_filter_sort_and_preserve_required_hierarchy(): void
    {
        $country = Country::factory()->withIso2('EG')->create();
        $division = Governorate::factory()->create(['country_id' => $country->id, 'code' => 'CA']);
        $city = City::factory()->create(['governorate_id' => $division->id, 'code' => 'NA', 'postal_code' => '12345']);
        Country::factory()->withIso2('US')->inactive()->create();
        $this->getJson('/api/v1/locations/countries?search=EG&sort=name')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $country->id);
        $this->getJson('/api/v1/locations/countries/'.$country->id.'/governorates?code=CA&sort=-name')->assertOk()->assertJsonPath('data.0.id', $division->id);
        $this->getJson('/api/v1/locations/governorates/'.$division->id.'/cities?postal_code=12345&sort=name')->assertOk()->assertJsonPath('data.0.id', $city->id);
        $this->admin();
        $this->getJson('/api/v1/admin/locations/countries?is_active=0&sort=-iso2')->assertOk()->assertJsonPath('data.0.iso2', 'US');
        $this->getJson('/api/v1/admin/locations/governorates?sort=name')->assertUnprocessable();
        $this->getJson('/api/v1/admin/locations/governorates?country_id='.$country->id.'&code=CA&sort=name')->assertOk()->assertJsonPath('data.0.id', $division->id);
        $this->getJson('/api/v1/admin/locations/cities?governorate_id='.$division->id.'&code=NA&sort=name')->assertOk()->assertJsonPath('data.0.id', $city->id);
    }

    public function test_public_page_filters_use_published_snapshot_and_admin_uses_draft(): void
    {
        $page = Page::factory()->create(['slug' => 'policy', 'show_in_footer' => false, 'en' => ['title' => 'Draft secret', 'content' => 'Draft']]);
        $revision = PageRevision::factory()->create(['page_id' => $page->id]);
        $page->forceFill(['status' => 'published', 'published_revision_id' => $revision->id])->save();
        $this->getJson('/api/v1/pages?search=Terms&show_in_footer=1&sort=title')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Terms');
        $this->getJson('/api/v1/pages?search=Draft')->assertOk()->assertJsonCount(0, 'data');
        $this->admin();
        $this->getJson('/api/v1/admin/pages?search=Draft&show_in_footer=0&status=published&sort=-title')->assertOk()->assertJsonPath('data.items.0.id', $page->id);
        $this->getJson('/api/v1/admin/pages/'.$page->id.'/revisions?version=1&sort=published_at')->assertOk()->assertJsonPath('data.items.0.id', $revision->id);
    }

    public function test_support_search_cannot_escape_owner_scope_and_admin_assignment_filters_work(): void
    {
        $user = User::factory()->client()->create();
        $own = SupportConversation::factory()->create(['user_id' => $user->id, 'subject' => 'Invoice help']);
        SupportConversation::factory()->create(['subject' => 'Invoice help']);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/client/support/conversations?search=Invoice&status[]=new&status[]=closed&sort=created_at')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $own->id);
        $this->getJson('/api/v1/client/support/conversations?assigned_to=1')->assertUnprocessable();
        $this->getJson('/api/v1/client/support/conversations/'.$own->id.'/messages?sort=-id')->assertUnprocessable();
        $this->getJson('/api/v1/client/support/conversations/'.$own->id.'/messages?after_id=0&per_page=1')->assertOk()->assertJsonPath('data.meta.has_more', false);
        $this->admin();
        $this->getJson('/api/v1/admin/support/conversations?user_id='.$user->id.'&unassigned=1&sort=-id')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $own->id);
        $this->getJson('/api/v1/admin/support/conversations?unassigned=1&assigned_to=1')->assertUnprocessable();
    }

    public function test_contact_lists_filter_status_and_search_with_dates(): void
    {
        $this->admin();
        $message = ContactMessage::factory()->create(['subject' => 'Billing question', 'status' => 'reviewed', 'created_at' => '2026-10-11 13:00:00']);
        ContactMessage::factory()->create(['subject' => 'Billing question', 'status' => 'closed']);
        $this->getJson('/api/v1/admin/contact-messages?search=Billing&status[]=new&status[]=reviewed&created_to=2026-10-11&sort=-updated_at')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $message->id);
    }

    public function test_notification_display_sort_and_dates_preserve_global_unread_count(): void
    {
        $user = User::factory()->client()->create();
        $this->travelTo('2026-10-10 12:00:00');
        app(NotificationOutbox::class)->record($user, 'notifications.phone_changed', [], 'old', 'security');
        $this->travelTo('2026-10-11 12:00:00');
        app(NotificationOutbox::class)->record($user, 'notifications.phone_changed', [], 'new', 'security');
        $latest = UserNotification::query()->where('event_key', 'new')->sole();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/client/notifications?category=security&created_from=2026-10-11&sort=created_at&sort_type=desc')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $latest->id)->assertJsonPath('data.unread_count', 2);
        $this->getJson('/api/v1/client/notifications?since_version=0&sort=id')->assertUnprocessable()->assertJsonPath('errors.sort.0', __('filters.sync'));
        $this->getJson('/api/v1/client/notifications?since_version=0&category=security')->assertUnprocessable();
        $this->getJson('/api/v1/client/notifications?until_version=1')->assertUnprocessable();
    }

    public function test_referral_history_filters_only_own_attribution_records(): void
    {
        $code = ReferralCode::factory()->create();
        $claim = Referral::factory()->create(['referral_code_id' => $code->id, 'referrer_id' => $code->user_id]);
        Referral::factory()->create();
        Sanctum::actingAs(User::findOrFail($code->user_id));
        $this->getJson('/api/v1/client/referrals/history?id='.$claim->id.'&sort=created_at&per_page=1')->assertOk()->assertJsonPath('data.items.0.id', $claim->id)->assertJsonPath('data.meta.total', 1);
        $this->getJson('/api/v1/client/referrals/history?status=registered')->assertUnprocessable();
    }

    public function test_myfatoorah_filters_only_local_metadata(): void
    {
        $this->admin();
        $operation = MyFatoorahOperation::factory()->create(['operation' => 'transfers.create']);
        $reconciled = MyFatoorahOperation::factory()->create(['status' => 'confirmed_failed']);
        $entity = MyFatoorahEntity::factory()->create(['needs_refresh' => true]);
        $this->getJson('/api/v1/admin/integrations/myfatoorah/operations?status=uncertain&operation=transfers.create&sort=created_at')->assertOk()->assertJsonPath('data.items.0.id', $operation->id);
        $this->getJson('/api/v1/admin/integrations/myfatoorah/entities?kind=supplier&needs_refresh=1&reference='.$entity->reference.'&sort=-synced_at')->assertOk()->assertJsonPath('data.items.0.id', $entity->id);
        $this->getJson('/api/v1/admin/integrations/myfatoorah/entities?snapshot=secret')->assertUnprocessable();
        $this->getJson('/api/v1/admin/integrations/myfatoorah/operations?status=confirmed_failed')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $reconciled->id);
    }

    public function test_session_search_and_current_filter_exclude_other_accounts_and_expired_tokens(): void
    {
        $this->travelTo('2026-10-11 12:00:00');
        $user = User::factory()->client()->create();
        $current = $user->createToken('Phone', ['*'], now()->addDay());
        $other = $user->createToken('Tablet', ['*'], now()->addDay());
        $user->createToken('Expired', ['*'], now()->subMinute());
        User::factory()->client()->create()->createToken('Tablet', ['*'], now()->addDay());
        $this->withToken($current->plainTextToken)->getJson('/api/v1/client/auth/sessions?is_current=0&search=Tablet&sort=-last_used_at')
            ->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $other->accessToken->id)->assertJsonPath('data.items.0.is_current', false);
        $this->withToken($current->plainTextToken)->getJson('/api/v1/client/auth/sessions?is_current=1')
            ->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $current->accessToken->id);
        $this->withToken($current->plainTextToken)->getJson('/api/v1/client/auth/sessions?search=Expired')->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_openapi_describes_sort_options_and_resource_specific_filters(): void
    {
        $this->app['env'] = 'local';
        config(['scramble.cache.store' => 'array']);
        $document = $this->getJson('/docs/api.json')->assertOk()->json();
        $parameters = collect($document['paths']['/admin/users']['get']['parameters'])->keyBy('name');
        foreach (['sort', 'sort_type', 'per_page', 'page', 'search', 'type', 'status', 'phone_verified', 'created_from', 'created_to'] as $field) {
            $this->assertTrue($parameters->has($field), $field);
        }
        $this->assertSame(['asc', 'desc'], $parameters['sort_type']['schema']['enum']);
        $this->assertStringContainsString('last_login_at', $parameters['sort']['description']);
        $this->assertCount(2, $parameters['status']['schema']['anyOf']);
        $messages = collect($document['paths']['/client/support/conversations/{conversation}/messages']['get']['parameters'])->keyBy('name');
        $this->assertFalse($messages->has('page'));
        $this->assertFalse($messages->has('sort'));
        $this->assertSame(50, $messages['per_page']['schema']['default']);
    }

    private function admin(): User
    {
        config(['auth.defaults.guard' => 'web']);
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->assignRole('super-admin');
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function payment(User $user, int $amount): Payment
    {
        $wallet = Wallet::query()->where('user_id', $user->id)->where('currency', 'EGP')->first() ?? Wallet::forceCreate(['user_id' => $user->id, 'currency' => 'EGP', 'balance' => 0]);

        return Payment::forceCreate(['uuid' => Str::uuid(), 'user_id' => $user->id, 'wallet_id' => $wallet->id, 'amount' => $amount, 'currency' => 'EGP', 'provider' => 'stripe', 'status' => 'paid', 'idempotency_key' => Str::uuid(), 'request_hash' => str_repeat('a', 64)]);
    }

    private function transaction(int $walletId, string $direction, int $amount): WalletTransaction
    {
        return WalletTransaction::forceCreate(['wallet_id' => $walletId, 'direction' => $direction, 'amount' => $amount, 'balance_after' => 1000, 'reason' => 'test', 'idempotency_key' => Str::uuid(), 'request_hash' => str_repeat('a', 64), 'created_at' => now()]);
    }
}
