<?php

namespace App\Actions\Notifications;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RegisterDeviceToken
{
    public function execute(User $user, #[\SensitiveParameter] string $token): DeviceToken
    {
        return DB::transaction(function () use ($user, $token): DeviceToken {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $fingerprint = hash('sha256', $token);
            $device = DeviceToken::query()->where('fingerprint', $fingerprint)->first();
            if ($device && $device->user_id !== $user->id) {
                throw new DomainException(ErrorCode::RESOURCE_CONFLICT);
            }
            if (! $device && DeviceToken::query()->where('user_id', $user->id)->count() >= 10) {
                throw new DomainException(ErrorCode::RATE_LIMITED);
            }
            $device ??= new DeviceToken;
            $device->forceFill(['user_id' => $user->id, 'token' => $token, 'fingerprint' => $fingerprint])->save();

            return $device;
        }, 5);
    }
}
