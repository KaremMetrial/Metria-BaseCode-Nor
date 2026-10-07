<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Notifications\RegisterDeviceToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\DeviceTokenRequest;
use App\Models\DeviceToken;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeviceTokenController extends Controller
{
    /**
     * Register a push device.
     *
     * Registers a Firebase device token for this account. A token owned by another account returns 409 RESOURCE_CONFLICT. Each account may register up to ten devices.
     */
    public function store(DeviceTokenRequest $request, RegisterDeviceToken $action): JsonResponse
    {
        $device = $action->execute($request->user(), $request->validated('token'));

        return ApiResponse::success(['id' => $device->id], __('notifications.device_registered'), 201);
    }

    /**
     * Remove a push device.
     *
     * Deletes a device owned by the caller. A different account’s device is not visible.
     */
    public function destroy(Request $request, int $device): JsonResponse
    {
        DeviceToken::query()->where('user_id', $request->user()->id)->findOrFail($device)->delete();

        return ApiResponse::success(null, __('notifications.device_removed'));
    }
}
