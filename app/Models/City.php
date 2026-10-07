<?php

namespace App\Models;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A city, belonging to exactly one governorate.
 *
 * There is deliberately no `country()` relation. Laravel's `hasOneThrough` only
 * traverses parent -> child -> grandchild, and this path runs the other way
 * (City -> Governorate -> Country), so no native relation expresses it. Rather
 * than hand-write a relation that subtly returns the wrong rows, reach the
 * country by eager-loading `governorate.country` -- doing so also makes the
 * required eager loads explicit at the call site.
 *
 * @property int $id
 * @property int $governorate_id
 * @property string|null $code
 * @property string|null $postal_code
 * @property float|null $latitude
 * @property float|null $longitude
 * @property bool $is_active
 * @property-read string $name
 */
#[Fillable([
    'governorate_id',
    'code',
    'postal_code',
    'latitude',
    'longitude',
    'is_active',
    'sort_order',
])]
class City extends Model implements TranslatableContract
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
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            // Cast to float so coordinates serialise as JSON numbers rather than
            // the decimal-as-string that a `decimal:7` cast would produce.
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<Governorate, $this>
     */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Builder<City>  $query
     * @return Builder<City>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<City>  $query
     * @return Builder<City>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<City>  $query
     * @return Builder<City>
     */
    public function scopeForGovernorate(Builder $query, int $governorateId): Builder
    {
        return $query->where('governorate_id', $governorateId);
    }
}
