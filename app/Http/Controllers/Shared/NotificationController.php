<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\ListNotificationsRequest;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class NotificationController extends Controller
{
    /**
     * List my notifications or synchronize changes.
     *
     * Filters by read state and category. Pass since_version to fetch changes including archived tombstones; pass its snapshot_version as until_version and next_cursor on subsequent pages; advance since_version only after all pages are consumed. Unread count excludes archived notifications.
     */
    public function index(ListNotificationsRequest $request, ListQuery $filters): JsonResponse
    {
        $data = $request->validated();

        $result = DB::transaction(function () use ($request, $data, $filters): array {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $query = UserNotification::query()->where('user_id', $user->id);
            if (isset($data['since_version'])) {
                $snapshot = min((int) ($data['until_version'] ?? $user->notifications_version), $user->notifications_version);
                $query->where('revision', '>', $data['since_version'])->where('revision', '<=', $snapshot)->orderBy('revision')->orderBy('id');
                $page = $query->cursorPaginate((int) ($data['per_page'] ?? 25));

                return ['items' => $page->getCollection()->map(fn (UserNotification $row): array => $row->payload()), 'meta' => ['next_cursor' => $page->nextCursor()?->encode(), 'snapshot_version' => $snapshot], 'unread_count' => $this->countUnread($user)];
            } else {
                $filters->apply($query->whereNull('archived_at'), $request);
                if (($data['status'] ?? 'all') === 'unread') {
                    $query->whereNull('read_at');
                } elseif (($data['status'] ?? 'all') === 'read') {
                    $query->whereNotNull('read_at');
                }
                if (isset($data['category'])) {
                    $query->where('category', $data['category']);
                }
            }
            $page = $query->paginate((int) ($data['per_page'] ?? 25));

            return ['items' => $page->getCollection()->map(fn (UserNotification $row): array => $row->payload()), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'snapshot_version' => $user->notifications_version], 'unread_count' => $this->countUnread($user)];
        }, 5);

        return ApiResponse::success($result);
    }

    /**
     * Count my unread notifications.
     *
     * Returns the current authoritative unread count and synchronization version across all devices.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            return ApiResponse::success(['unread_count' => $this->countUnread($user), 'version' => $user->notifications_version]);
        }, 5);
    }

    /**
     * Mark one notification as read.
     *
     * Repeated requests preserve the original read timestamp. Another account's notification returns 404.
     */
    public function read(Request $request, string $notification): JsonResponse
    {
        return $this->change($request, $notification, 'read_at');
    }

    /**
     * Archive one notification.
     *
     * Removes the notification from the inbox and unread count. Synchronization responses retain an archived tombstone for other devices.
     */
    public function archive(Request $request, string $notification): JsonResponse
    {
        return $this->change($request, $notification, 'archived_at');
    }

    /**
     * Mark notifications from a known snapshot as read.
     *
     * Send through_version from the list response. Notifications created after that snapshot remain unread, even if this request is retried.
     */
    public function readAll(Request $request): JsonResponse
    {
        $data = $request->validate(['through_version' => ['required', 'integer', 'min:0']]);

        return DB::transaction(function () use ($request, $data): JsonResponse {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $changed = UserNotification::query()->where('user_id', $user->id)->whereNull('archived_at')->whereNull('read_at')
                ->where('sequence', '<=', min((int) $data['through_version'], $user->notifications_version))
                ->update(['read_at' => now(), 'updated_at' => now(), 'revision' => $user->notifications_version + 1]);
            if ($changed) {
                $user->increment('notifications_version', 1, ['notifications_dirty' => true]);
            }

            return ApiResponse::success(['updated' => $changed, 'unread_count' => $this->countUnread($user), 'version' => $user->notifications_version]);
        }, 5);
    }

    private function change(Request $request, string $notification, string $field): JsonResponse
    {
        return DB::transaction(function () use ($request, $notification, $field): JsonResponse {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $row = UserNotification::query()->where('user_id', $user->id)->findOrFail($notification);
            if ($row->getAttribute($field) === null) {
                $user->increment('notifications_version', 1, ['notifications_dirty' => true]);
                $row->forceFill([$field => now(), 'revision' => $user->notifications_version])->save();
            }

            return ApiResponse::success($row->payload());
        }, 5);
    }

    private function countUnread(User $user): int
    {
        return UserNotification::query()->where('user_id', $user->id)->whereNull('archived_at')->whereNull('read_at')->count();
    }
}
