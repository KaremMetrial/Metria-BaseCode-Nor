<?php

namespace App\Services\Auth;

use App\Models\Page;
use App\Services\Content\TermsService;

final class ClientRequirements
{
    public function __construct(private TermsService $terms) {}

    /** @return array{terms_version: string, terms_revision_id: ?int, terms_url: ?string, recovery: array{method: string, support_url: ?string}} */
    public function current(): array
    {
        $terms = $this->terms->current();
        $supportUrl = Page::query()->where('slug', 'contact-us')->where('status', 'published')->exists()
            ? route('pages.show', ['slug' => 'contact-us', 'locale' => app()->getLocale()])
            : config('client.support_url');

        return ['terms_version' => $terms['version'], 'terms_revision_id' => $terms['revision_id'], 'terms_url' => $terms['url'], 'recovery' => ['method' => 'support', 'support_url' => $supportUrl ?: null]];
    }
}
