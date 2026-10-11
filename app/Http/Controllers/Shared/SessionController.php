<?php

namespace App\Http\Controllers\Shared;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Services\Auth\AccountSessions;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

final class SessionController extends Controller
{
    /**
     * List my active sessions.
     *
     * Returns device names and activity timestamps, never bearer tokens. Expired sessions are excluded.
     */
    public function index(Request $request, AccountSessions $sessions): JsonResponse
    {
        $current = $sessions->current($request->user());
        $items = $request->user()->tokens()->orderByDesc('id')->get()
            ->filter(fn (PersonalAccessToken $token): bool => $sessions->isValid($token))
            ->map(fn (PersonalAccessToken $token): array => ['id' => $token->id, 'device_name' => $token->name, 'is_current' => $token->id === $current->id, 'created_at' => $token->created_at->toIso8601String(), 'last_used_at' => $token->last_used_at?->toIso8601String(), 'expires_at' => $token->expires_at?->toIso8601String()])->values();

        return ApiResponse::success(['items' => $items]);
    }

    /**
     * Revoke one of my sessions.
     *
     * Also removes its push subscription. A session belonging to another account returns 404.
     */
    public function destroy(Request $request, int $session, AccountSessions $sessions, AuditLoggerInterface $audit): JsonResponse
    {
        DB::transaction(function () use ($request, $session, $sessions, $audit): void {
            $sessions->revoke($request->user(), $session);
            $audit->record(new AuditEntry(AuditAction::USER_SESSION_REVOKED, $request->user(), actor: $request->user()));
        }, 5);

        return ApiResponse::success();
    }

    /**
     * Sign out on every device.
     *
     * Revokes all bearer tokens and push subscriptions, including this session.
     */
    public function destroyAll(Request $request, AccountSessions $sessions, AuditLoggerInterface $audit): JsonResponse
    {
        DB::transaction(function () use ($request, $sessions, $audit): void {
            $sessions->revoke($request->user());
            $audit->record(new AuditEntry(AuditAction::USER_LOGOUT, $request->user(), actor: $request->user()));
        }, 5);

        return ApiResponse::success();
    }
}
