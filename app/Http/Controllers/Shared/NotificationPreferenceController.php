<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\NotificationPreferenceRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class NotificationPreferenceController extends Controller
{
    /**
     * Read my notification preferences.
     *
     * Marketing push is disabled until explicitly enabled. Security notifications always remain available in the notification center.
     */
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(['preferences' => $request->user()->notificationPreferences()]);
    }

    /**
     * Update my notification preferences.
     *
     * Merges only supplied channel preferences. Pending deliveries recheck these preferences before sending.
     */
    public function update(NotificationPreferenceRequest $request): JsonResponse
    {
        $preferences = DB::transaction(function () use ($request): array {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $preferences = array_replace($user->notificationPreferences(), array_map(fn ($value): bool => (bool) $value, $request->validated('preferences')));
            $user->forceFill(['notification_preferences' => $preferences])->save();

            return $preferences;
        }, 5);

        return ApiResponse::success(['preferences' => $preferences]);
    }
}
