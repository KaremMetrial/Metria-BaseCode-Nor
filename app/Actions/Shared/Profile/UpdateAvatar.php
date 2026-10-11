<?php

namespace App\Actions\Shared\Profile;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class UpdateAvatar
{
    public function execute(User $user, ?UploadedFile $file): User
    {
        $path = $file?->store('avatars/'.$user->id, 'local');
        if ($path === false) {
            throw new RuntimeException('Avatar storage failed.');
        }
        try {
            return DB::transaction(function () use ($user, $path): User {
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $old = $locked->avatar_path;
                $locked->forceFill(['avatar_path' => $path])->save();
                if ($old) {
                    DB::afterCommit(fn () => Storage::disk('local')->delete($old));
                }

                return $locked;
            }, 5);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }
}
