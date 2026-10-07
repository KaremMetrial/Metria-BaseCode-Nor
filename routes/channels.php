<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', fn ($user, $userId): bool => $user->isActive() && (string) $user->id === (string) $userId);
