<?php

namespace Database\Seeders;

use App\Actions\Client\Auth\CompleteProfile;
use App\Enums\UserType;
use App\Models\Country;
use App\Models\User;
use App\Services\Content\TermsService;
use App\Support\Access\RoleRegistry;
use RuntimeException;

final class DemoUserSeeder extends DevelopmentSeeder
{
    public const ACCOUNTS = [
        'client' => ['name' => 'عميل تجريبي', 'email' => null, 'phone' => '+201000000101', 'type' => 'client', 'status' => 'active', 'locale' => 'ar'],
        'referred' => ['name' => 'Referred Demo Client', 'email' => null, 'phone' => '+201000000102', 'type' => 'client', 'status' => 'active', 'locale' => 'en'],
        'onboarding' => ['name' => '', 'email' => null, 'phone' => '+201000000103', 'type' => 'client', 'status' => 'active', 'locale' => 'ar'],
        'blocked' => ['name' => 'Blocked Demo Client', 'email' => null, 'phone' => '+201000000104', 'type' => 'client', 'status' => 'blocked', 'locale' => 'en'],
        'vendor' => ['name' => 'Demo Vendor', 'email' => 'vendor@demo.example.test', 'phone' => '+201000000201', 'type' => 'vendor', 'status' => 'active', 'locale' => 'en'],
        'pending_vendor' => ['name' => 'Pending Demo Vendor', 'email' => 'pending-vendor@demo.example.test', 'phone' => '+201000000202', 'type' => 'vendor', 'status' => 'pending', 'locale' => 'ar'],
        'support' => ['name' => 'Demo Support', 'email' => 'support@demo.example.test', 'phone' => null, 'type' => 'admin', 'status' => 'active', 'locale' => 'ar'],
        'finance' => ['name' => 'Demo Finance', 'email' => 'finance@demo.example.test', 'phone' => null, 'type' => 'admin', 'status' => 'active', 'locale' => 'en'],
    ];

    protected function seed(): void
    {
        $country = Country::query()->where('iso2', 'EG')->firstOrFail();
        $terms = app(TermsService::class)->current();
        foreach (self::ACCOUNTS as $key => $data) {
            $identity = $data['email'] === null ? ['phone' => $data['phone']] : ['email' => $data['email']];
            $existing = User::withTrashed()->where($identity)->first();
            if ($existing) {
                if ($existing->type->value !== $data['type']) {
                    throw new RuntimeException('Demo account identity collides with a different account type: '.$key);
                }

                continue;
            }
            $user = new User;
            $user->forceFill([...$data, 'password' => $data['type'] === 'admin' ? config('access.dev_admin.password') : null,
                'email_verified_at' => null, 'phone_country_id' => $data['phone'] ? $country->id : null,
                'phone_verified_at' => $data['phone'] ? now() : null, 'timezone' => 'Africa/Cairo',
                'referral_claim_expires_at' => $data['type'] === 'client' ? now()->addHours((int) config('links.referral_claim_hours')) : null])->save();
            if (in_array($key, ['support', 'finance'], true)) {
                $user->assignRole($key === 'support' ? RoleRegistry::SUPPORT_AGENT : RoleRegistry::FINANCE_ADMIN);
            }
            if ($user->type === UserType::CLIENT && ! in_array($key, ['onboarding', 'referred'], true)) {
                app(CompleteProfile::class)->execute($user, ['name' => $data['name'], 'terms_version' => $terms['version'],
                    'terms_revision_id' => $terms['revision_id'], 'terms_accepted' => true]);
            }
        }
    }
}
