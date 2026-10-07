<?php

namespace App\Models;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A country in which the product operates.
 *
 * Translated columns live in `country_translations` (Astrotomic). English is the
 * default locale and its translation is mandatory; other locales are optional.
 *
 * Scope boundary: this model answers "do we operate here and how is it
 * displayed?". It does not validate phone numbers -- that is libphonenumber's
 * job, reached through PhoneNumberServiceInterface.
 *
 * @property int $id
 * @property string $iso2
 * @property string|null $iso3
 * @property string|null $numeric_code
 * @property string $calling_code
 * @property bool $is_active
 * @property-read string $name
 * @property-read string|null $nationality
 */
#[Fillable([
    'iso2',
    'iso3',
    'numeric_code',
    'calling_code',
    'currency_code',
    'timezone_default',
    'is_active',
    'sort_order',
    'phone_example',
])]
class Country extends Model implements TranslatableContract
{
    use HasFactory;
    use Translatable;

    /**
     * Translated columns.
     *
     * Astrotomic reads this property but does not declare it, so every
     * translatable model must.
     *
     * @var list<string>
     */
    public array $translatedAttributes = ['name', 'nationality'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return HasMany<Governorate, $this>
     */
    public function governorates(): HasMany
    {
        return $this->hasMany(Governorate::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Builder<Country>  $query
     * @return Builder<Country>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Country>  $query
     * @return Builder<Country>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        // iso2 breaks ties deterministically so pagination cannot reshuffle
        // equal sort_order rows between requests.
        return $query->orderBy('sort_order')->orderBy('iso2');
    }

    /**
     * @param  Builder<Country>  $query
     * @return Builder<Country>
     */
    public function scopeIso2(Builder $query, string $iso2): Builder
    {
        return $query->where('iso2', strtoupper($iso2));
    }

    /**
     * @param  Builder<Country>  $query
     * @return Builder<Country>
     */
    public function scopeActiveFirst(Builder $query): Builder
    {
        return $query->orderByDesc('is_active');
    }
}
