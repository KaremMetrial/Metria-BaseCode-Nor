<?php

namespace App\Actions\Client\Auth;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class CompleteProfile
{
    /** @param array{name: string, terms_version: string, locale?: string, timezone?: ?string, terms_accepted: mixed} $data */
    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->fill(Arr::only($data, ['name', 'locale', 'timezone']));
            if ($locked->terms_version !== $data['terms_version'] || $locked->terms_accepted_at === null) {
                $locked->forceFill(['terms_version' => $data['terms_version'], 'terms_accepted_at' => now()]);
            }
            $locked->forceFill(['profile_completed_at' => $locked->profile_completed_at ?? now()])->save();

            return $locked;
        }, 5);
    }
}
