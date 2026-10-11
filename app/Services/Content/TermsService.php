<?php

namespace App\Services\Content;

use App\Models\Page;

final class TermsService
{
    /** @return array{version: string, url: ?string, revision_id: ?int} */
    public function current(bool $lock = false): array
    {
        $query = Page::query()->where('slug', 'terms');
        $page = ($lock ? $query->lockForUpdate() : $query)->first();
        if ($page?->published_revision_id) {
            return ['version' => $page->terms_version, 'url' => route('pages.show', ['slug' => 'terms', 'locale' => app()->getLocale()]), 'revision_id' => $page->published_revision_id];
        }

        return ['version' => (string) config('client.terms_version'), 'url' => config('client.terms_url'), 'revision_id' => null];
    }
}
