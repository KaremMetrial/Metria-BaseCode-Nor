<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationController extends Controller
{
    /**
     * List my notifications.
     *
     * Returns the caller’s notifications, newest first, with read timestamps.
     */
    public function index(Request $request): JsonResponse
    {
        $page = UserNotification::query()->where('user_id', $request->user()->id)->orderByDesc('created_at')->orderByDesc('id')->paginate(25);

        return ApiResponse::success(['items' => $page->getCollection()->map(fn ($row) => $row->payload()), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => 25, 'total' => $page->total()]]);
    }

    /**
     * Mark a notification as read.
     *
     * Sets read_at once. Repeating the request preserves the original timestamp. Other accounts’ notifications are not visible.
     */
    public function read(Request $request, string $notification): JsonResponse
    {
        $row = UserNotification::query()->where('user_id', $request->user()->id)->findOrFail($notification);
        $row->forceFill(['read_at' => $row->read_at ?? now()])->save();

        return ApiResponse::success($row->payload());
    }
}
