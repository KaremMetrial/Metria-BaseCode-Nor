<?php

namespace App\Http\Resources\Admin;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Page */
final class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'slug' => $this->slug, 'status' => $this->status, 'draft_version' => $this->draft_version,
            'media_ids' => $this->media_ids ?? [], 'sort_order' => $this->sort_order, 'show_in_footer' => $this->show_in_footer, 'show_in_settings' => $this->show_in_settings,
            'translations' => $this->translations->mapWithKeys(fn ($row): array => [$row->locale => ['title' => $row->title, 'content' => $row->content]]),
            'published_revision_id' => $this->published_revision_id, 'terms_version' => $this->terms_version,
            'published_version' => $this->publishedRevision?->version, 'updated_at' => $this->updated_at->toIso8601String()];
    }
}
