<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Shared\ValidatesTranslations;
use App\Models\City;
use App\Support\Access\PermissionRegistry;
use Illuminate\Validation\Rule;

class StoreCityRequest extends AdminFormRequest
{
    use ValidatesTranslations;

    protected function permission(): string
    {
        return PermissionRegistry::LOCATIONS_MANAGE;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('code')) {
            return;
        }

        $code = $this->input('code');

        $this->merge([
            'code' => is_string($code) ? strtoupper(trim($code)) : $code,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->cityRules(partial: false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function cityRules(bool $partial): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $record = $this->route('city');
        $ignore = $record instanceof City ? $record->getKey() : null;

        // See StoreGovernorateRequest: on PATCH the parent may be omitted from
        // the payload, so fall back to the record's current governorate rather
        // than scoping uniqueness to `null`.
        $governorateId = $this->input('governorate_id')
            ?? ($record instanceof City ? $record->governorate_id : null);

        return array_merge([
            'governorate_id' => [
                $required, 'integer',
                Rule::exists('governorates', 'id'),
            ],

            'code' => [
                'nullable', 'string', 'max:16',
                Rule::unique('cities', 'code')
                    ->where(fn ($query) => $query->where('governorate_id', $governorateId))
                    ->ignore($ignore),
            ],

            'postal_code' => ['nullable', 'string', 'max:16'],

            // Ranges are the real geographic bounds. Accepting 91 would store a
            // coordinate that no map can render.
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ], $this->translationRules(['name'], [], $partial));
    }
}
