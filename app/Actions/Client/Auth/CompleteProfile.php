<?php

namespace App\Actions\Client\Auth;

use App\Models\PageRevision;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Services\Content\TermsService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompleteProfile
{
    /** @param array{name: string, terms_version: string, terms_revision_id?: ?int, locale?: string, timezone?: ?string, terms_accepted: mixed} $data */
    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $terms = app(TermsService::class)->current(lock: true);
            if ($data['terms_version'] !== $terms['version']) {
                throw ValidationException::withMessages(['terms_version' => [__('content.terms_changed')]]);
            }
            $revisionId = $data['terms_revision_id'] ?? null;
            if (($terms['revision_id'] !== null && ! $revisionId) || ($revisionId !== null && ! PageRevision::query()->whereKey($revisionId)->where('terms_version', $terms['version'])->exists())) {
                throw ValidationException::withMessages(['terms_revision_id' => [__('content.terms_changed')]]);
            }
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->fill(Arr::only($data, ['name', 'locale', 'timezone']));
            if ($locked->terms_version !== $data['terms_version'] || $locked->terms_accepted_at === null) {
                $locked->forceFill(['terms_version' => $data['terms_version'], 'terms_accepted_at' => now()]);
            }
            if (! TermsAcceptance::query()->where('user_id', $locked->id)->where('terms_version', $data['terms_version'])->exists()) {
                $acceptance = new TermsAcceptance;
                $acceptance->forceFill(['user_id' => $locked->id, 'terms_version' => $data['terms_version'], 'page_revision_id' => $revisionId, 'accepted_at' => $locked->terms_accepted_at])->save();
            }
            $locked->forceFill(['profile_completed_at' => $locked->profile_completed_at ?? now()])->save();

            return $locked;
        }, 5);
    }
}
