<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Shared\Auth\VerifyPhoneLogin;
use App\Enums\OtpPurpose;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\OtpRequest;
use App\Http\Requests\Shared\VerifyOtpRequest;
use App\Http\Resources\Shared\UserResource;
use App\Services\Auth\AuthenticationPhone;
use App\Services\Auth\OtpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class OtpController extends Controller
{
    /**
     * Request a sign-in code.
     *
     * Send the selected country ID and a mobile phone number. Returns a challenge ID; the code is delivered by SMS and is never included in this response.
     */
    public function request(OtpRequest $request, AuthenticationPhone $phones, OtpService $otp): JsonResponse
    {
        $data = $request->validated();
        $phone = $phones->parse((int) $data['country_id'], $data['phone']);
        $type = UserType::from($request->route('actor_type'));
        $id = $otp->issue($phone->e164, (int) $data['country_id'], $type, OtpPurpose::LOGIN, null, app()->getLocale());

        return ApiResponse::success(['challenge_id' => $id, 'expires_in' => (int) config('otp.ttl_seconds'), 'resend_after' => (int) config('otp.cooldown_seconds')], __('otp.sent'));
    }

    /**
     * Verify a sign-in code.
     *
     * Submit the same country and phone with the challenge ID and six-digit code. Creates an account when needed and returns its profile and bearer token. For clients, next_step identifies whether profile completion is required; expires_at identifies token expiry. Optional device_id rotates an existing session for that installation. New vendors await administrator approval.
     */
    public function verify(VerifyOtpRequest $request, VerifyPhoneLogin $action): JsonResponse
    {
        $result = $action->execute($request->validated(), UserType::from($request->route('actor_type')));

        return ApiResponse::success(['user' => new UserResource($result['user']), 'token' => $result['token'], 'expires_at' => $result['expires_at'], 'next_step' => $result['next_step']], __('auth.login'));
    }
}
