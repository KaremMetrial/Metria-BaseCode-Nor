<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\Auth\CloseAccount;
use App\Http\Controllers\Controller;
use App\Services\Auth\ClientRequirements;
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
    public function requirements(ClientRequirements $requirements): JsonResponse
    {
        return ApiResponse::success($requirements->current());
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
