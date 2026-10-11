<?php

namespace App\Actions\Admin\Content;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublishPage
{
    public function __construct(private readonly AuditLoggerInterface $audit) {}

    public function execute(Page $page, int $draftVersion, bool $requiresAcceptance, User $actor): Page
    {
        return DB::transaction(function () use ($page, $draftVersion, $requiresAcceptance, $actor): Page {
            $locked = Page::query()->lockForUpdate()->findOrFail($page->id);
            if ($locked->draft_version !== $draftVersion) {
                throw new DomainException(ErrorCode::RESOURCE_CONFLICT);
            }
            $translations = $locked->translations->mapWithKeys(fn ($row): array => [$row->locale => ['title' => $row->title, 'content' => $row->content]])->all();
            foreach (['en', 'ar'] as $locale) {
                if (! isset($translations[$locale]) || trim($translations[$locale]['title']) === '' || trim($translations[$locale]['content']) === '') {
                    throw ValidationException::withMessages(['translations.'.$locale => [__('content.publication_incomplete')]]);
                }
            }
            $version = ((int) $locked->revisions()->max('version')) + 1;
            $termsVersion = $locked->terms_version;
            if ($locked->slug === 'terms' && ($requiresAcceptance || $termsVersion === null)) {
                $termsVersion = 'terms-'.$locked->id.'-'.$version;
            }
            $revision = new PageRevision;
            $revision->forceFill(['page_id' => $locked->id, 'version' => $version, 'translations' => $translations,
                'navigation' => ['sort_order' => $locked->sort_order, 'show_in_footer' => $locked->show_in_footer, 'show_in_settings' => $locked->show_in_settings],
                'media_ids' => $locked->media_ids ?? [], 'terms_version' => $termsVersion, 'published_by' => $actor->id, 'published_at' => now()])->save();
            $locked->forceFill(['status' => 'published', 'published_revision_id' => $revision->id, 'terms_version' => $termsVersion, 'draft_version' => $locked->draft_version + 1])->save();
            $this->audit->record(new AuditEntry(AuditAction::PAGE_PUBLISHED, $locked, actor: $actor, context: ['revision_id' => $revision->id]));

            return $locked->fresh(['translations', 'publishedRevision']);
        }, 5);
    }

    public function archive(Page $page, int $draftVersion, User $actor): Page
    {
        return DB::transaction(function () use ($page, $draftVersion, $actor): Page {
            $locked = Page::query()->lockForUpdate()->findOrFail($page->id);
            if ($locked->draft_version !== $draftVersion) {
                throw new DomainException(ErrorCode::RESOURCE_CONFLICT);
            }
            if ($locked->slug === 'terms' && $locked->published_revision_id) {
                throw new DomainException(ErrorCode::RESOURCE_IN_USE);
            }
            $locked->forceFill(['status' => 'archived', 'draft_version' => $locked->draft_version + 1])->save();
            $this->audit->record(new AuditEntry(AuditAction::PAGE_ARCHIVED, $locked, actor: $actor));

            return $locked->fresh(['translations', 'publishedRevision']);
        }, 5);
    }
}
