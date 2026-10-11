<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Auth\LoginAdmin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Resources\Shared\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class AdminLoginController extends Controller
{
    /**
     * Sign in as an administrator.
     *
     * Returns a bearer token and the administrator profile. Invalid credentials or a disabled account return 401.
     */
    public function __invoke(AdminLoginRequest $request, LoginAdmin $action): JsonResponse
    {
        $result = $action->execute($request->validated('email'), $request->validated('password'));

        return ApiResponse::success(['user' => new UserResource($result['user']), 'token' => $result['token']], __('auth.login'));
    }
}
