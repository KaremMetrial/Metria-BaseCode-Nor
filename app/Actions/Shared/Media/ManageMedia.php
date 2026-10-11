<?php

namespace App\Actions\Shared\Media;

use App\Enums\ErrorCode;
use App\Enums\UserType;
use App\Exceptions\DomainException;
use App\Models\Media;
use App\Models\Page;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class ManageMedia
{
    public function __construct(private readonly InspectUpload $inspect) {}

    /** Caller holds the user row lock. Legacy files remain on their original disk. */
    public function importAvatar(User $user): ?Media
    {
        if (! $user->avatar_path) {
            return null;
        }
        $storage = Storage::disk('local');
        $exists = $storage->exists($user->avatar_path);
        $media = new Media;
        $media->forceFill(['user_id' => $user->id, 'purpose' => 'avatar', 'status' => 'attached',
            'disk' => 'local', 'path' => $user->avatar_path, 'original_name' => basename($user->avatar_path),
            'mime_type' => $exists ? $storage->mimeType($user->avatar_path) : 'image/png',
            'size' => $exists ? $storage->size($user->avatar_path) : 0, 'inspection' => 'legacy'])->save();

        return $media;
    }

    public function upload(User $actor, UploadedFile $file, string $purpose): Media
    {
        $data = $this->inspect->execute($file);
        $disk = (string) config('media.disk');
        if ($disk === 'public' || config('filesystems.disks.'.$disk.'.visibility') === 'public') {
            throw new RuntimeException('Media requires a private filesystem disk.');
        }
        $media = DB::transaction(function () use ($actor, $file, $purpose, $data, $disk): Media {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $owned = Media::query()->where('user_id', $actor->id);
            if ((clone $owned)->whereIn('status', ['pending', 'ready'])->count() >= (int) config('media.max_pending')
                || (clone $owned)->sum('size') + strlen($data['contents']) > (int) config('media.quota_bytes')) {
                throw ValidationException::withMessages(['file' => [__('media.quota')]]);
            }
            $media = new Media;
            $media->forceFill(['user_id' => $actor->id, 'purpose' => $purpose, 'status' => 'pending', 'disk' => $disk,
                'path' => 'media/'.$actor->id.'/'.Str::uuid().'.'.$data['extension'],
                'original_name' => Str::limit(preg_replace('/[\x00-\x1F\x7F\/\\\\]/u', '_', $file->getClientOriginalName()) ?: 'file', 200, ''),
                'mime_type' => $data['mime_type'], 'size' => strlen($data['contents']), 'sha256' => hash('sha256', $data['contents']),
                'width' => $data['width'], 'height' => $data['height'], 'inspection' => $data['inspection'],
                'expires_at' => now()->addHours((int) config('media.temporary_hours'))])->save();

            return $media;
        }, 5);
        try {
            if (! Storage::disk($media->disk)->put($media->path, $data['contents'], ['visibility' => 'private'])) {
                throw new RuntimeException('Media storage failed.');
            }
            $media->forceFill(['status' => 'ready'])->save();
        } catch (Throwable $exception) {
            $media->forceFill(['status' => 'deleting'])->save();
            throw $exception;
        }

        return $media;
    }

    /** @param list<string> $ids */
    public function attachMessage(User $actor, SupportMessage $message, array $ids): void
    {
        foreach ($this->lockUploads($actor, $ids, 'support') as $media) {
            $media->forceFill(['support_message_id' => $message->id, 'status' => 'attached', 'expires_at' => null])->save();
        }
    }

    /** @param list<string> $ids */
    public function attachPage(User $actor, Page $page, array $ids): void
    {
        foreach ($this->lockUploads($actor, $ids, 'page', $page->id) as $media) {
            $media->forceFill(['page_id' => $page->id, 'status' => 'attached', 'expires_at' => null])->save();
        }
        $removed = array_diff($page->media_ids ?? [], $ids);
        $page->forceFill(['media_ids' => $ids])->save();
        foreach ($removed as $id) {
            if (! $page->revisions()->whereJsonContains('media_ids', $id)->exists()) {
                Media::query()->whereKey($id)->where('page_id', $page->id)->update(['page_id' => null, 'status' => 'deleting']);
            }
        }
    }

    /**
     * Caller holds the parent lock in a transaction; cleanup locks these same media rows.
     *
     * @param  list<string>  $ids
     * @return Collection<int, Media>
     */
    private function lockUploads(User $actor, array $ids, string $purpose, ?int $pageId = null): Collection
    {
        $items = Media::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        if ($items->count() !== count($ids)) {
            throw ValidationException::withMessages(['media_ids' => [__('media.unavailable')]]);
        }
        foreach ($items as $media) {
            $existingPageAsset = $pageId !== null && $media->page_id === $pageId && $media->status === 'attached';
            if ($media->purpose !== $purpose || ! $media->isAvailable()
                || (! $existingPageAsset && ($media->user_id !== $actor->id || $media->status !== 'ready'))) {
                throw ValidationException::withMessages(['media_ids' => [__('media.unavailable')]]);
            }
        }

        return $items;
    }

    public function canRead(User $actor, Media $media): bool
    {
        if (! $actor->isActive() || ! $media->isAvailable()) {
            return false;
        }
        if ($media->purpose === 'support' && $media->support_message_id) {
            if ($actor->isAdmin()) {
                return $actor->can('support.read');
            }

            return ($actor->type === UserType::CLIENT) && $media->message?->conversation?->user_id === $actor->id;
        }
        if ($media->purpose === 'page') {
            return $actor->isAdmin() && $actor->can('pages.read') && ($media->page_id !== null || $media->user_id === $actor->id);
        }

        return $media->user_id === $actor->id;
    }

    public function discard(User $actor, Media $media): void
    {
        DB::transaction(function () use ($actor, $media): void {
            $locked = Media::query()->lockForUpdate()->findOrFail($media->id);
            abort_unless($locked->user_id === $actor->id, 404);
            if ($locked->status === 'attached') {
                throw new DomainException(ErrorCode::RESOURCE_IN_USE);
            }
            if ($locked->status === 'pending') {
                throw new DomainException(ErrorCode::RESOURCE_CONFLICT);
            }
            $locked->forceFill(['status' => 'deleting'])->save();
        }, 5);
    }

    public function prune(Media $media): bool
    {
        return DB::transaction(function () use ($media): bool {
            $locked = Media::query()->lockForUpdate()->find($media->id);
            if (! $locked || ($locked->status !== 'deleting' && ! ($locked->expires_at?->isPast() && in_array($locked->status, ['ready', 'pending'], true)))) {
                return false;
            }
            if (User::withTrashed()->where('avatar_media_id', $locked->id)->exists() || $locked->page_id || $locked->support_message_id) {
                return false;
            }
            $locked->forceFill(['status' => 'deleting'])->save();
            if (! Storage::disk($locked->disk)->delete($locked->path)) {
                return false;
            }
            $locked->delete();

            return true;
        }, 5);
    }
}
