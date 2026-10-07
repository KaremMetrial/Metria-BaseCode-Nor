<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\Realtime\SocketTokenService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SocketTokenController extends Controller
{
    public function __invoke(Request $request, SocketTokenService $tokens): JsonResponse
    {
        return ApiResponse::success($tokens->issue($request->user()));
    }
}
