<?php

namespace App\Http\Controllers\Client;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ShareLinkRequest;
use App\Http\Resources\Shared\ShareLinkResource;
use App\Models\ShareLink;
use App\Services\Links\ShareLinks;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ShareLinkController extends Controller
{
    /**
     * Create a public content share link.
     *
     * Requires a completed client profile. Targets are home, a published page or a visible category.
     * include_referral defaults to true. Each request creates a new link; no arbitrary redirect URLs are accepted.
     */
    public function store(ShareLinkRequest $request, ShareLinks $links): JsonResponse
    {
        $target = $request->validated('target');
        $link = $links->create($request->user(), $target['type'], (string) ($target['slug'] ?? $target['id'] ?? ''), $request->boolean('include_referral', true));

        return ApiResponse::success(new ShareLinkResource($link), status: 201);
    }

    /**
     * Revoke one of my share links.
     *
     * Idempotent. The link stops resolving publicly; existing referral claims are retained.
     * Revoking a share link does not revoke the reusable account referral code.
     */
    public function destroy(Request $request, string $code, AuditLoggerInterface $audit): JsonResponse
    {
        DB::transaction(function () use ($request, $code, $audit): void {
            $link = ShareLink::query()->where('code', $code)->where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            if ($link->revoked_at === null) {
                $link->forceFill(['revoked_at' => now()])->save();
                $audit->record(new AuditEntry(AuditAction::SHARE_LINK_REVOKED, $link, actor: $request->user()));
            }
        }, 5);

        return ApiResponse::success();
    }
}
