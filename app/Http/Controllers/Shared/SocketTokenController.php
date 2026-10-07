<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\Realtime\SocketTokenService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SocketTokenController extends Controller
{
    /**
     * Issue a realtime connection token.
     *
     * Returns a short-lived signed Socket.IO token and its Unix expiry time. Use it for the caller’s private notification room. It cannot authenticate REST API requests.
     */
    public function __invoke(Request $request, SocketTokenService $tokens): JsonResponse
    {
        return ApiResponse::success($tokens->issue($request->user()));
    }
}
