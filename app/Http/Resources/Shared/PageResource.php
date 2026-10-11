<?php

namespace App\Http\Resources\Shared;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin Page */
final class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $revision = $this->publishedRevision;
        $translation = $revision->translations[app()->getLocale()];

        return ['slug' => $this->slug, 'title' => $translation['title'], 'content' => $translation['content'],
            'content_html' => (string) Str::markdown($translation['content'], ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'media' => array_map(fn (string $id): array => ['id' => $id, 'url' => route('api.v1.media.public', ['media' => $id])], $revision->media_ids ?? []), 'version' => $revision->version, 'revision_id' => $revision->id, 'terms_version' => $revision->terms_version, 'navigation' => $revision->navigation,
            'published_at' => $revision->published_at->toIso8601String(), 'url' => route('pages.show', ['slug' => $this->slug, 'locale' => app()->getLocale()])];
    }
}
