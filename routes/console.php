<?php

use App\Jobs\DeliverNotificationChannel;
use App\Jobs\PublishNotification;
use App\Models\NotificationDelivery;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('notifications:publish-pending', function (): void {
    UserNotification::query()->whereNull('published_at')->orderBy('id')->limit(1000)->get(['id'])->each(fn ($row) => PublishNotification::dispatch($row->id));
    NotificationDelivery::query()->where('status', 'pending')->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))->orderBy('id')->limit(1000)->get(['id'])->each(fn ($row) => DeliverNotificationChannel::dispatch($row->id));
})->purpose('Dispatch pending notification outbox records');
Schedule::command('notifications:publish-pending')->everyMinute()->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();

Schedule::command('payments:reconcile --limit=25')->everyFiveMinutes()->withoutOverlapping(60);
