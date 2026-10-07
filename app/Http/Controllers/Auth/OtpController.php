<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\VerifyPhoneLogin;
use App\Enums\OtpPurpose;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\Shared\UserResource;
use App\Services\Auth\AuthenticationPhone;
use App\Services\Auth\OtpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class OtpController extends Controller
{
    public function request(OtpRequest $request, AuthenticationPhone $phones, OtpService $otp): JsonResponse
    {
        $data = $request->validated();
        $phone = $phones->parse((int) $data['country_id'], $data['phone']);
        $type = UserType::from($request->route('actor_type'));
        $id = $otp->issue($phone->e164, (int) $data['country_id'], $type, OtpPurpose::LOGIN, null, app()->getLocale());

        return ApiResponse::success(['challenge_id' => $id], __('otp.sent'));
    }

    public function verify(VerifyOtpRequest $request, VerifyPhoneLogin $action): JsonResponse
    {
        $result = $action->execute($request->validated(), UserType::from($request->route('actor_type')));

        return ApiResponse::success(['user' => new UserResource($result['user']), 'token' => $result['token']], __('auth.login'));
    }
}
