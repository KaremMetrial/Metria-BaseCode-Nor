<?php

namespace Database\Seeders;

use App\Actions\Admin\Content\PublishPage;
use App\Models\Media;
use App\Models\Page;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class DemoMediaSeeder extends DevelopmentSeeder
{
    /** A real, embedded PNG fixture; never downloaded from an external service. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAIAAAD8GO2jAAAAKklEQVR4nGNQSX5NU8QwasGoBaMWjFowasGoBaMWjFowasGoBaMWDBULAH3FyExHFf5eAAAAAElFTkSuQmCC';

    protected function seed(): void
    {
        if (config('filesystems.disks.local.driver') !== 'local' || config('filesystems.disks.local.visibility') === 'public') {
            throw new RuntimeException('Demo media requires a private local filesystem disk.');
        }
        foreach (['admin', 'client', 'vendor'] as $key) {
            $user = $this->user($key);
            if ($user->avatar_media_id || $user->avatar_path) {
                continue;
            }
            $media = $this->image($user, 'avatar', $key.'-avatar');
            $media->forceFill(['status' => 'attached', 'expires_at' => null])->save();
            $user->forceFill(['avatar_media_id' => $media->id, 'avatar_path' => $media->path])->save();
        }
        $client = $this->user('client');
        $conversation = SupportConversation::query()->where('user_id', $client->id)->where('request_id', '20000000-0000-4000-8000-000000000002')->firstOrFail();
        $message = $conversation->messages()->orderBy('id')->firstOrFail();
        $attachment = $this->image($client, 'support', 'support-attachment');
        if ($attachment->wasRecentlyCreated) {
            $attachment->forceFill(['support_message_id' => $message->id, 'status' => 'attached', 'expires_at' => null])->save();
        }
        $this->image($client, 'support', 'unattached-upload');

        $page = Page::query()->where('slug', 'demo-help')->firstOrFail();
        $media = $this->image($this->user('admin'), 'page', 'page-image');
        if ($media->wasRecentlyCreated) {
            $media->forceFill(['page_id' => $page->id, 'status' => 'attached', 'expires_at' => null])->save();
            $page->forceFill(['media_ids' => [...($page->media_ids ?? []), $media->id]])->save();
            foreach (['en', 'ar'] as $locale) {
                $translation = $page->translateOrNew($locale);
                $translation->fill(['content' => $translation->getAttribute('content')."\n\n![Demo PNG](".route('api.v1.media.public', ['media' => $media->id], absolute: false).')']);
            }
            $page->save();
            app(PublishPage::class)->execute($page, $page->draft_version, false, $this->user('admin'));
        }
    }

    private function image(User $owner, string $purpose, string $name): Media
    {
        $contents = base64_decode(self::PNG, strict: true);
        $path = 'demo/'.$owner->id.'/'.$name.'.png';
        $storage = Storage::disk('local');
        if (! $storage->exists($path) && ! $storage->put($path, $contents, ['visibility' => 'private'])) {
            throw new RuntimeException('Unable to write demo media: '.$path);
        }
        if (hash('sha256', $storage->get($path)) !== hash('sha256', $contents)) {
            throw new RuntimeException('Demo media path already contains another file: '.$path);
        }

        return Media::query()->firstOrCreate(['path' => $path], ['user_id' => $owner->id, 'purpose' => $purpose,
            'status' => 'ready', 'disk' => 'local', 'original_name' => $name.'.png', 'mime_type' => 'image/png',
            'size' => strlen($contents), 'sha256' => hash('sha256', $contents), 'width' => 32, 'height' => 32,
            'inspection' => 'demo_fixture', 'expires_at' => now()->addHours((int) config('media.temporary_hours'))]);
    }
}
