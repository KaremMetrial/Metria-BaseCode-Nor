<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginAdmin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Resources\Shared\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class AdminLoginController extends Controller
{
    public function __invoke(AdminLoginRequest $request, LoginAdmin $action): JsonResponse
    {
        $result = $action->execute($request->validated('email'), $request->validated('password'));

        return ApiResponse::success(['user' => new UserResource($result['user']), 'token' => $result['token']], __('auth.login'));
    }
}
