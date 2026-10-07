<?php

namespace App\Models;

use App\Enums\GovernorateType;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A first-level administrative division (governorate, state, province, emirate
 * or region -- recorded in `type`).
 *
 * @property int $id
 * @property int $country_id
 * @property string|null $code
 * @property GovernorateType|null $type  (see casts())
 * @property bool $is_active
 * @property-read string $name
 */
#[Fillable([
    'country_id',
    'code',
    'type',
    'is_active',
    'sort_order',
])]
class Governorate extends Model implements TranslatableContract
{
    use HasFactory;
    use Translatable;

    /**
     * @var list<string>
     */
    public array $translatedAttributes = ['name'];

    protected function casts(): array
    {
        return [
            'type' => GovernorateType::class,
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
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<City, $this>
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Builder<Governorate>  $query
     * @return Builder<Governorate>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Governorate>  $query
     * @return Builder<Governorate>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<Governorate>  $query
     * @return Builder<Governorate>
     */
    public function scopeForCountry(Builder $query, int $countryId): Builder
    {
        return $query->where('country_id', $countryId);
    }
}
