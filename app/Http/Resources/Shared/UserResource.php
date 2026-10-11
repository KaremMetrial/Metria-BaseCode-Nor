<?php

namespace App\Http\Resources\Shared;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin User */
final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->when($this->type !== UserType::CLIENT, $this->email), 'phone' => $this->phone, 'phone_country_id' => $this->phone_country_id, 'phone_verified_at' => $this->phone_verified_at?->toIso8601String(), 'type' => $this->type->value, 'status' => $this->status->value, 'locale' => $this->locale, 'timezone' => $this->timezone, 'has_avatar' => $this->avatar_path !== null, 'avatar_url' => $this->avatar_path ? Storage::disk('local')->temporaryUrl($this->avatar_path, now()->addMinutes(5)) : null, 'profile_completed' => $this->when($this->type === UserType::CLIENT, fn (): bool => $this->resource->hasCompletedProfile()), 'terms_version' => $this->when($this->type === UserType::CLIENT, $this->terms_version)];
    }
}
