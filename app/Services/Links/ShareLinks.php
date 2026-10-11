<?php

namespace App\Services\Links;

use App\Enums\ErrorCode;
use App\Enums\UserType;
use App\Exceptions\DomainException;
use App\Models\Category;
use App\Models\Page;
use App\Models\ReferralCode;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ShareLinks
{
    public function create(User $actor, string $type, string $value, bool $withReferral): ShareLink
    {
        return DB::transaction(function () use ($actor, $type, $value, $withReferral): ShareLink {
            $owner = User::query()->lockForUpdate()->findOrFail($actor->id);
            $this->assertCanShare($owner);
            $this->destination($type, $value);
            $referral = $withReferral ? $this->codeFor($owner) : null;

            return $this->insert($owner, $type, $value, $referral);
        }, 5);
    }

    public function referralLink(User $actor): ShareLink
    {
        return DB::transaction(function () use ($actor): ShareLink {
            $owner = User::query()->lockForUpdate()->findOrFail($actor->id);
            $this->assertCanShare($owner);
            $code = $this->codeFor($owner);

            return ShareLink::query()->where('user_id', $owner->id)->where('referral_code_id', $code->id)
                ->where('target_type', 'home')->whereNull('revoked_at')->orderBy('id')->first()
                ?? $this->insert($owner, 'home', '', $code);
        }, 5);
    }

    public function find(string $code): ShareLink
    {
        $link = ShareLink::query()->where('code', $code)->whereNull('revoked_at')->firstOrFail();
        $owner = User::query()->active()->ofType(UserType::CLIENT)->find($link->user_id);
        abort_unless($owner !== null, 404);
        $this->destination($link->target_type, $link->target_value);

        return $link;
    }

    public function url(ShareLink $link): string
    {
        return rtrim((string) (config('links.base_url') ?: config('app.url')), '/').route('share.show', ['code' => $link->code], absolute: false);
    }

    /** @return array{type: string, id: ?int, slug: ?string, title: string, description: string, web_url: ?string} */
    public function destination(string $type, string $value): array
    {
        $target = ['type' => $type, 'id' => null, 'slug' => null, 'title' => (string) config('app.name'), 'description' => __('links.description'), 'web_url' => null];
        if ($type === 'page') {
            $page = Page::query()->where('slug', $value)->where('status', 'published')->with('publishedRevision')->firstOrFail();
            $translation = $page->publishedRevision?->translations[app()->getLocale()] ?? $page->publishedRevision?->translations[config('languages.fallback')] ?? null;
            abort_unless($translation !== null, 404);
            $target['slug'] = $page->slug;
            $target['title'] = $translation['title'];
            $target['description'] = Str::limit(strip_tags((string) Str::markdown($translation['content'], ['html_input' => 'strip', 'allow_unsafe_links' => false])), 180);
            $target['web_url'] = rtrim((string) config('app.url'), '/').route('pages.show', ['slug' => $page->slug, 'locale' => app()->getLocale()], absolute: false);
        } elseif ($type === 'category') {
            $category = Category::query()->visible()->with('translations')->findOrFail((int) $value);
            $target['id'] = $category->id;
            $target['title'] = $category->name;
        } else {
            abort_unless($type === 'home', 404);
        }

        return $target;
    }

    /** @return array{android: ?string, ios: ?string} */
    public function stores(ShareLink $link): array
    {
        $android = $this->storeUrl('android');
        if ($android !== null) {
            $parts = parse_url($android);
            parse_str($parts['query'] ?? '', $query);
            $query['referrer'] = http_build_query(['link_code' => $link->code]);
            $android = 'https://play.google.com'.$parts['path'].'?'.http_build_query($query);
        }

        return ['android' => $android, 'ios' => $this->storeUrl('ios')];
    }

    private function storeUrl(string $platform): ?string
    {
        $url = config('links.'.$platform.'_store_url');
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $parts = parse_url($url);
        $host = $platform === 'android' ? 'play.google.com' : 'apps.apple.com';
        if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== $host || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }
        if ($platform === 'android') {
            parse_str($parts['query'] ?? '', $query);
            if (($parts['path'] ?? '') !== '/store/apps/details' || ! is_string($query['id'] ?? null) || $query['id'] === '') {
                return null;
            }
        } elseif (! preg_match('~/id[0-9]+$~', $parts['path'] ?? '')) {
            return null;
        }

        return $url;
    }

    private function assertCanShare(User $owner): void
    {
        if ($owner->type !== UserType::CLIENT || ! $owner->isActive() || ! $owner->hasCompletedProfile()) {
            throw new DomainException(ErrorCode::FORBIDDEN);
        }
    }

    private function codeFor(User $owner): ReferralCode
    {
        $code = ReferralCode::query()->where('user_id', $owner->id)->first();
        if (! $code) {
            $code = new ReferralCode;
            $code->forceFill(['user_id' => $owner->id, 'code' => strtoupper(bin2hex(random_bytes(8)))])->save();
        }

        return $code;
    }

    private function insert(User $owner, string $type, string $value, ?ReferralCode $referral): ShareLink
    {
        $link = new ShareLink;
        $link->forceFill(['code' => bin2hex(random_bytes(16)), 'user_id' => $owner->id, 'referral_code_id' => $referral?->id, 'target_type' => $type, 'target_value' => $value])->save();

        return $link;
    }
}
