<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Shared\Profile\ChangePhone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\OtpRequest;
use App\Http\Requests\Shared\VerifyOtpRequest;
use App\Http\Resources\Shared\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PhoneChangeController extends Controller
{
    /**
     * Request a phone change.
     *
     * Clients must have verified their current phone within the last five minutes, through login or reauthentication. Sends a verification code to the proposed new mobile number. The number must not already belong to another account.
     */
    public function request(OtpRequest $request, ChangePhone $action): JsonResponse
    {
        return ApiResponse::success(['challenge_id' => $action->request($request->user(), $request->validated()), 'expires_in' => (int) config('otp.ttl_seconds'), 'resend_after' => (int) config('otp.cooldown_seconds')], __('otp.sent'));
    }

    /**
     * Confirm a phone change.
     *
     * On success, changes the phone number and revokes ALL bearer tokens, including the current token. Sign in again with the new number.
     */
    public function verify(VerifyOtpRequest $request, ChangePhone $action): JsonResponse
    {
        return ApiResponse::success(new UserResource($action->verify($request->user(), $request->validated())), __('auth.phone_changed'));
    }
}
