<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Shared\ValidatesTranslations;
use App\Models\Country;
use App\Support\Access\PermissionRegistry;
use Illuminate\Validation\Rule;

class StoreCountryRequest extends AdminFormRequest
{
    use ValidatesTranslations;

    protected function permission(): string
    {
        return PermissionRegistry::LOCATIONS_MANAGE;
    }

    /**
     * ISO codes are case-insensitive to humans but must be stored in exactly one
     * case. Without normalising, "eg" and "EG" are two distinct values that the
     * unique index happily accepts, and the same country can be imported twice.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['iso2', 'iso3', 'currency_code', 'calling_code'] as $key) {
            if ($this->exists($key)) {
                $normalized[$key] = $key === 'calling_code' ? $this->trimmedString($key) : $this->uppercaseString($key);
            }
        }
        $this->merge($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->countryRules(partial: false);
    }

    /**
     * Shared by store and update so the two can never drift apart.
     *
     * @return array<string, mixed>
     */
    protected function countryRules(bool $partial): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $record = $this->route('country');
        $ignore = $record instanceof Country ? $record->getKey() : null;

        return array_merge([
            'iso2' => [
                $required, 'string', 'size:2', 'regex:/^[A-Z]{2}$/',
                Rule::unique('countries', 'iso2')->ignore($ignore),
            ],
            'iso3' => [
                'nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/',
                Rule::unique('countries', 'iso3')->ignore($ignore),
            ],
            'numeric_code' => ['nullable', 'string', 'size:3', 'regex:/^[0-9]{3}$/'],

            // Display metadata. Deliberately NOT unique: +1 covers the US and
            // Canada, +44 covers several territories.
            'calling_code' => [$required, 'string', 'max:8', 'regex:/^\+[0-9]{1,4}$/'],

            'currency_code' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'timezone_default' => ['nullable', 'string', 'timezone'],
            'phone_example' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ], $this->translationRules(['name'], ['nationality'], $partial));
    }

    private function uppercaseString(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) ? strtoupper(trim($value)) : $value;
    }

    private function trimmedString(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) ? trim($value) : $value;
    }
}
