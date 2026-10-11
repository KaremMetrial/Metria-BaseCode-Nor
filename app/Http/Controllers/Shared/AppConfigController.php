<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\ContactSetting;
use App\Services\Auth\ClientRequirements;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class AppConfigController extends Controller
{
    /**
     * Read public application startup settings.
     *
     * No authentication required. Returns supported languages, client registration requirements,
     * OTP timing, contact channels and upload limits. Send X-Locale explicitly and retain the
     * ETag for If-None-Match requests; unchanged settings return 304 without a response body.
     * OTP challenge responses remain authoritative for expiry and resend timing.
     */
    public function show(ClientRequirements $requirements): JsonResponse
    {
        return ApiResponse::success([
            'localization' => [
                'default' => (string) config('languages.default'),
                'fallback' => (string) config('languages.fallback'),
                'current' => (string) app()->getLocale(),
                'header' => (string) config('languages.header'),
                /** @var list<array{code: string, name: string, native_name: string, rtl: bool}> */
                'supported' => $this->languages(),
            ],
            'client_auth' => [
                /** @var array{terms_version: string, terms_revision_id: ?int, terms_url: ?string, recovery: array{method: string, support_url: ?string}} */
                'requirements' => $requirements->current(),
                'otp' => [
                    'expires_in_seconds' => (int) config('otp.ttl_seconds'),
                    'resend_after_seconds' => (int) config('otp.cooldown_seconds'),
                ],
            ],
            /** @var array{phone: ?string, whatsapp: ?string, social_links: list<array{label: string, url: string}>, address: ?string, working_hours: ?string, intro: ?string} */
            'contact' => $this->contact(),
            'uploads' => [
                'avatar' => $this->uploadLimits('avatar'),
                'support' => $this->uploadLimits('support'),
                'page' => $this->uploadLimits('page'),
            ],
        ])->withHeaders(['Vary' => 'X-Locale, Accept-Language, Authorization, Cookie']);
    }

    /** @return list<array{code: string, name: string, native_name: string, rtl: bool}> */
    private function languages(): array
    {
        $languages = [];
        foreach (config('languages.supported', []) as $code => $language) {
            $languages[] = ['code' => $code, 'name' => $language['name'], 'native_name' => $language['native_name'], 'rtl' => $language['rtl']];
        }

        return $languages;
    }

    /** @return array{phone: ?string, whatsapp: ?string, social_links: list<array{label: string, url: string}>, address: ?string, working_hours: ?string, intro: ?string} */
    private function contact(): array
    {
        $settings = ContactSetting::query()->find(1)?->publicSettings() ?? [];

        return [
            'phone' => $settings['phone'] ?? null,
            'whatsapp' => $settings['whatsapp'] ?? null,
            'social_links' => array_map(fn (array $link): array => ['label' => $link['label'], 'url' => $link['url']], $settings['social_links'] ?? []),
            'address' => $settings['address'] ?? null,
            'working_hours' => $settings['working_hours'] ?? null,
            'intro' => $settings['intro'] ?? null,
        ];
    }

    /** @return array{max_size_bytes: int, extensions: list<string>, image_max_width: int, image_max_height: int} */
    private function uploadLimits(string $purpose): array
    {
        $limits = config('media.uploads.'.$purpose);

        return [
            'max_size_bytes' => (int) ($limits['max_size_kb'] * 1024),
            /** @var list<string> */
            'extensions' => $limits['extensions'],
            'image_max_width' => (int) $limits['max_width'],
            'image_max_height' => (int) $limits['max_height'],
        ];
    }
}
