<?php

namespace App\Console\Commands;

use App\Actions\Shared\Media\ManageMedia;
use App\Models\Media;
use Illuminate\Console\Command;
use Throwable;

class PruneMedia extends Command
{
    protected $signature = 'media:prune {--limit=500}';

    protected $description = 'Remove expired unattached uploads and retry pending file deletions';

    public function handle(ManageMedia $action): int
    {
        $failed = false;
        $items = Media::query()->where(fn ($query) => $query->where('status', 'deleting')->orWhere(fn ($query) => $query->whereIn('status', ['ready', 'pending'])->where('expires_at', '<', now())))->orderBy('updated_at')->limit(max(1, min(5000, (int) $this->option('limit'))))->get();
        foreach ($items as $media) {
            try {
                if (! $action->prune($media)) {
                    $media->touch();
                    $failed = true;
                }
            } catch (Throwable $exception) {
                report($exception);
                $media->touch();
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
