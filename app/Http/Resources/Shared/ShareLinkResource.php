<?php

namespace App\Http\Resources\Shared;

use App\Models\ReferralCode;
use App\Models\ShareLink;
use App\Services\Links\ShareLinks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShareLink */
final class ShareLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $links = app(ShareLinks::class);
        $destination = $links->destination($this->target_type, $this->target_value);

        return [
            'code' => $this->code,
            /** @var string */
            'url' => $links->url($this->resource),
            /** @var array{type: string, id: ?int, slug: ?string} */
            'target' => ['type' => $destination['type'], 'id' => $destination['id'], 'slug' => $destination['slug']],
            'requires_auth' => false,
            /** @var string */
            'title' => $destination['title'],
            /** @var string */
            'description' => $destination['description'],
            /** @var string|null */
            'web_url' => $destination['web_url'],
            'referral_code' => $this->referral_code_id ? ReferralCode::query()->find($this->referral_code_id)?->code : null,
            /** @var array{android: ?string, ios: ?string} */
            'stores' => $links->stores($this->resource),
        ];
    }
}
