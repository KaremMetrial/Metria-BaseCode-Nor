<?php

namespace App\Http\Requests\Shared;

/**
 * Builds validation rules for the `translations` payload.
 *
 * Payload shape (see spec section 39):
 *
 *   "translations": {
 *     "en": { "name": "Egypt", "nationality": "Egyptian" },
 *     "ar": { "name": "مصر",   "nationality": "مصري" }
 *   }
 *
 * Astrotomic understands this shape natively, but only when
 * `translatable.translations_wrapper` is configured (it defaults to null).
 *
 * Four decisions are encoded here, each guarding a specific failure:
 *
 *  1. `array:en,ar` restricts the locale *keys*, so an unsupported locale is
 *     rejected instead of stored and never returned.
 *  2. The default locale's NOT NULL columns are mandatory on create. A record
 *     that cannot render in the default locale is a bug; requiring every locale
 *     would block a launch on translation availability, so others are optional.
 *  3. NOT NULL columns (`$requiredColumns`) are never marked `nullable`, even on
 *     a partial update. `nullable` would accept an explicit null and then hit a
 *     database constraint error -- a 500 instead of a 422.
 *  4. `required_array_keys:name` means that supplying a locale object at all
 *     means supplying its name. Without it, `"translations": {"ar": {}}` would
 *     create an empty translation row and fail at the database.
 */
trait ValidatesTranslations
{
    /**
     * @param  list<string>  $requiredColumns  Columns that are NOT NULL in the translation table.
     * @param  list<string>  $nullableColumns  Columns that accept null.
     * @return array<string, list<string>>
     */
    protected function translationRules(
        array $requiredColumns,
        array $nullableColumns = [],
        bool $partial = false,
    ): array {
        $locales = $this->supportedLocales();
        $default = $this->defaultLocale();

        $rules = [
            'translations' => [
                $partial ? 'sometimes' : 'required',
                'array:'.implode(',', $locales),
            ],
        ];

        foreach ($locales as $locale) {
            $isDefault = $locale === $default;

            $localeRules = ['sometimes', 'array:'.implode(',', array_merge($requiredColumns, $nullableColumns))];

            if ($requiredColumns !== []) {
                // If this locale block is present at all, its NOT NULL columns
                // must be present too.
                $localeRules[] = 'required_array_keys:'.implode(',', $requiredColumns);
            }

            $rules["translations.{$locale}"] = $localeRules;

            foreach ($requiredColumns as $column) {
                $rules["translations.{$locale}.{$column}"] = [
                    $isDefault && ! $partial ? 'required' : 'sometimes',
                    'string',
                    'max:255',
                ];
            }

            foreach ($nullableColumns as $column) {
                $rules["translations.{$locale}.{$column}"] = ['nullable', 'string', 'max:255'];
            }
        }

        return $rules;
    }

    /**
     * @return list<string>
     */
    protected function supportedLocales(): array
    {
        return array_keys(config('languages.supported'));
    }

    protected function defaultLocale(): string
    {
        return (string) config('languages.default');
    }
}
