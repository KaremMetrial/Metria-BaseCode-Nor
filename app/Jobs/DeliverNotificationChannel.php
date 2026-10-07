<?php

namespace App\Jobs;

use App\Services\Notifications\DeliverNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class DeliverNotificationChannel implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 40;

    public array $backoff = [30, 120, 300, 600];

    public function __construct(public readonly int $deliveryId)
    {
        $this->afterCommit();
    }

    public function handle(DeliverNotification $delivery): void
    {
        $delivery->execute($this->deliveryId);
    }
}
