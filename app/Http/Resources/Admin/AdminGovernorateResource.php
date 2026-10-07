<?php

namespace App\Http\Resources\Admin;

use App\Models\Governorate;
use App\Models\GovernorateTranslation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A governorate as an administrator sees it (all translations, admin fields).
 *
 * @mixin Governorate
 */
class AdminGovernorateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_id' => $this->country_id,
            'code' => $this->code,
            'type' => $this->type?->value,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,

            'translations' => $this->whenLoaded('translations', fn () => $this->translations
                ->mapWithKeys(fn (GovernorateTranslation $translation) => [
                    $translation->locale => ['name' => $translation->name],
                ])
                ->all()),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
