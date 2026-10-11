<?php

namespace App\Console\Commands;

use App\Actions\Shared\Media\ManageMedia;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyAvatars extends Command
{
    protected $signature = 'media:import-avatars';

    protected $description = 'Register existing local avatars without moving or replacing their files';

    public function handle(ManageMedia $action): int
    {
        User::withTrashed()->whereNotNull('avatar_path')->whereNull('avatar_media_id')->chunkById(100, function ($users) use ($action): void {
            foreach ($users as $user) {
                DB::transaction(function () use ($user, $action): void {
                    $locked = User::withTrashed()->lockForUpdate()->findOrFail($user->id);
                    if ($locked->avatar_path && ! $locked->avatar_media_id) {
                        $media = $action->importAvatar($locked);
                        $locked->forceFill(['avatar_media_id' => $media?->id])->save();
                    }
                }, 5);
            }
        });

        return self::SUCCESS;
    }
}
