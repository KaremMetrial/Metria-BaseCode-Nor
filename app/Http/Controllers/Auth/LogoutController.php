<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\Logout;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutController extends Controller
{
    /**
     * Sign out.
     *
     * Revokes the current bearer token. Other sessions remain valid.
     */
    public function __invoke(Request $request, Logout $action): JsonResponse
    {
        $action->execute($request->user());

        return ApiResponse::success(null, __('auth.logout'));
    }
}
