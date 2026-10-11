<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PageRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, array{title: string, content: string}> $translations
 * @property array{sort_order: int, show_in_footer: bool, show_in_settings: bool} $navigation
 * @property CarbonImmutable $published_at
 */
class PageRevision extends Model
{
    /** @use HasFactory<PageRevisionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['media_ids' => 'array', 'translations' => 'array', 'navigation' => 'array', 'version' => 'integer', 'published_at' => 'immutable_datetime'];
    }
}
