<?php

namespace App\Http\Requests\Admin;

use App\Enums\GovernorateType;
use App\Http\Requests\Shared\ValidatesTranslations;
use App\Models\Governorate;
use App\Support\Access\PermissionRegistry;
use Illuminate\Validation\Rule;

class StoreGovernorateRequest extends AdminFormRequest
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
        return $this->governorateRules(partial: false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function governorateRules(bool $partial): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $record = $this->route('governorate');
        $ignore = $record instanceof Governorate ? $record->getKey() : null;

        // On PATCH the client may omit country_id, but the record still belongs
        // to a country. Scoping the uniqueness check to `null` would silently
        // permit a duplicate code within the record's real country and defer the
        // failure to a database constraint error.
        $countryId = $this->input('country_id')
            ?? ($record instanceof Governorate ? $record->country_id : null);

        return array_merge([
            'country_id' => [
                $required, 'integer',
                // Prevents an orphaned division pointing at nothing.
                Rule::exists('countries', 'id'),
            ],
            'type' => ['nullable', Rule::enum(GovernorateType::class)],

            // `nullable` is load-bearing here: it makes the validator skip the
            // unique check entirely when no code is supplied. Without it, every
            // code-less governorate would collide with the others on
            // `code = NULL` and the import would fail.
            'code' => [
                'nullable', 'string', 'max:16',
                Rule::unique('governorates', 'code')
                    ->where(fn ($query) => $query->where('country_id', $countryId))
                    ->ignore($ignore),
            ],

            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ], $this->translationRules(['name'], [], $partial));
    }
}
