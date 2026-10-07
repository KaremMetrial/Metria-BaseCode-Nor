<?php
use Illuminate\Support\Facades\{Artisan,Schedule};
Artisan::command('notifications:publish-pending',function(): void {
 App\Models\UserNotification::query()->whereNull('published_at')->orderBy('id')->limit(1000)->get(['id'])->each(fn($row)=>App\Jobs\PublishNotification::dispatch($row->id));
})->purpose('Dispatch pending notification outbox records');
Schedule::command('notifications:publish-pending')->everyMinute()->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
