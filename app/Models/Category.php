<?php

namespace App\Models;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $name
 * @property-read string|null $description
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CategoryTranslation> $translations
 */
#[Fillable(['parent_id', 'is_active', 'sort_order'])]
class Category extends Model implements TranslatableContract
{
    use Translatable;

    public const MAX_DEPTH = 5;

    public array $translatedAttributes = ['name', 'description'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeVisible(Builder $query, int $remaining = self::MAX_DEPTH): Builder
    {
        return $query->where('is_active', true)->where(function (Builder $q) use ($remaining): void {
            $q->whereNull('parent_id');
            if ($remaining > 1) {
                $q->orWhereHas('parent', fn (Builder $p) => $p->visible($remaining - 1));
            }
        });
    }
}
