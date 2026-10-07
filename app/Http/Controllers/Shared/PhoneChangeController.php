<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Profile\ChangePhone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\Shared\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PhoneChangeController extends Controller
{
    public function request(OtpRequest $request, ChangePhone $action): JsonResponse
    {
        return ApiResponse::success(['challenge_id' => $action->request($request->user(), $request->validated())], __('otp.sent'));
    }

    public function verify(VerifyOtpRequest $request, ChangePhone $action): JsonResponse
    {
        return ApiResponse::success(new UserResource($action->verify($request->user(), $request->validated())), __('auth.phone_changed'));
    }
}
