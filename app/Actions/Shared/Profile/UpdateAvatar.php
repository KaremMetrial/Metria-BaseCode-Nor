<?php

namespace App\Actions\Shared\Profile;

use App\Actions\Shared\Media\ManageMedia;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateAvatar
{
    public function __construct(private readonly ManageMedia $media) {}

    public function execute(User $user, ?UploadedFile $file): User
    {
        $upload = $file ? $this->media->upload($user, $file, 'avatar') : null;

        return DB::transaction(function () use ($user, $upload): User {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $old = $locked->avatar_media_id ? Media::query()->findOrFail($locked->avatar_media_id) : $this->media->importAvatar($locked);
            if ($upload) {
                $upload = Media::query()->lockForUpdate()->findOrFail($upload->id);
                if (! $upload->isAvailable() || $upload->status !== 'ready') {
                    throw ValidationException::withMessages(['avatar' => [__('media.unavailable')]]);
                }
                $upload->forceFill(['status' => 'attached', 'expires_at' => null])->save();
            }
            $locked->forceFill(['avatar_path' => $upload?->path, 'avatar_media_id' => $upload?->id])->save();
            if ($old) {
                $old->forceFill(['status' => 'deleting'])->save();
            }

            return $locked;
        }, 5);
    }
}
