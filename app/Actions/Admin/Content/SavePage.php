<?php

namespace App\Actions\Admin\Content;

use App\Actions\Shared\Media\ManageMedia;
use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class SavePage
{
    public function __construct(private readonly ManageMedia $media) {}

    /** @param array<string, mixed> $data */
    public function execute(?Page $page, array $data, User $actor): Page
    {
        return DB::transaction(function () use ($page, $data, $actor): Page {
            if ($page) {
                $page = Page::query()->lockForUpdate()->findOrFail($page->id);
                if ($page->draft_version !== (int) $data['draft_version']) {
                    throw new DomainException(ErrorCode::RESOURCE_CONFLICT);
                }
                $page->draft_version++;
            } else {
                $page = new Page;
            }
            $page->fill(Arr::only($data, ['slug', 'sort_order', 'show_in_footer', 'show_in_settings']));
            foreach ($data['translations'] ?? [] as $locale => $translation) {
                $page->translateOrNew($locale)->fill(['title' => $translation['title'], 'content' => $translation['content'] ?? '']);
            }
            $page->save();
            if (array_key_exists('media_ids', $data)) {
                $this->media->attachPage($actor, $page, $data['media_ids']);
            }

            return $page->fresh(['translations', 'publishedRevision']);
        }, 5);
    }
}
