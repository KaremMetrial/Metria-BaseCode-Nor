<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\Auth\CloseAccount;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Content\TermsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccountController extends Controller
{
    /**
     * Read registration requirements and recovery guidance.
     *
     * Clients use phone authentication only. Lost-phone recovery requires support identity verification; this endpoint never changes an account.
     */
    public function requirements(): JsonResponse
    {
        $terms = app(TermsService::class)->current();
        $supportUrl = Page::query()->where('slug', 'contact-us')->where('status', 'published')->exists()
            ? route('pages.show', ['slug' => 'contact-us', 'locale' => app()->getLocale()])
            : config('client.support_url');

        return ApiResponse::success(['terms_version' => $terms['version'], 'terms_revision_id' => $terms['revision_id'], 'terms_url' => $terms['url'], 'recovery' => ['method' => 'support', 'support_url' => $supportUrl ?: null]]);
    }

    /**
     * Close my client account.
     *
     * Requires recent phone verification and explicit confirmation. Balances and pending payments or refunds block closure. Financial records are retained.
     */
    public function destroy(Request $request, CloseAccount $action): JsonResponse
    {
        $request->validate(['confirm' => ['required', 'accepted']]);
        $action->execute($request->user());

        return ApiResponse::success();
    }
}
