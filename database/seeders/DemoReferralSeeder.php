<?php

namespace Database\Seeders;

use App\Actions\Client\Auth\CompleteProfile;
use App\Actions\Client\Referrals\ClaimReferral;
use App\Models\Category;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ShareLink;
use App\Services\Content\TermsService;
use App\Services\Links\ShareLinks;

final class DemoReferralSeeder extends DevelopmentSeeder
{
    protected function seed(): void
    {
        $client = $this->user('client');
        $links = app(ShareLinks::class);
        $home = $links->referralLink($client);
        $category = Category::query()->whereHas('translations', fn ($query) => $query->where('locale', 'en')->where('name', 'Demo coffee'))->firstOrFail();
        foreach (['page' => 'demo-help', 'category' => (string) $category->id] as $type => $value) {
            if (! ShareLink::query()->where('user_id', $client->id)->where('target_type', $type)->where('target_value', $value)->exists()) {
                $links->create($client, $type, $value, false);
            }
        }
        $referred = $this->user('referred');
        if (! Referral::query()->where('referred_user_id', $referred->id)->exists()) {
            $code = ReferralCode::query()->findOrFail($home->referral_code_id);
            app(ClaimReferral::class)->execute($referred, $code->code);
            $terms = app(TermsService::class)->current();
            app(CompleteProfile::class)->execute($referred, ['name' => $referred->name, 'terms_version' => $terms['version'],
                'terms_revision_id' => $terms['revision_id'], 'terms_accepted' => true]);
        }
    }
}
