<?php

namespace App\Models;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Collection<int, PageTranslation> $translations
 * @property PageRevision|null $publishedRevision
 */
class Page extends Model implements TranslatableContract
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use Translatable;

    public array $translatedAttributes = ['title', 'content'];

    protected $fillable = ['slug', 'sort_order', 'show_in_footer', 'show_in_settings'];

    protected function casts(): array
    {
        return ['media_ids' => 'array', 'draft_version' => 'integer', 'sort_order' => 'integer', 'show_in_footer' => 'boolean', 'show_in_settings' => 'boolean'];
    }

    /** @return BelongsTo<PageRevision, $this> */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(PageRevision::class, 'published_revision_id');
    }

    /** @return HasMany<PageRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class);
    }
}
