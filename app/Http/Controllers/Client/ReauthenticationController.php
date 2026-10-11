<?php

namespace App\Http\Controllers\Client;

use App\Enums\ErrorCode;
use App\Enums\OtpPurpose;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AccountSessions;
use App\Services\Auth\OtpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReauthenticationController extends Controller
{
    /**
     * Request confirmation of the current phone.
     *
     * Sends an OTP to the verified phone already attached to this account. The caller cannot select another number.
     */
    public function request(Request $request, AccountSessions $sessions, OtpService $otp): JsonResponse
    {
        $user = $request->user();
        $sessions->current($user);
        if (! $user->phone_country_id || ! $user->hasVerifiedPhone()) {
            throw new DomainException(ErrorCode::PHONE_NOT_VERIFIED);
        }
        $id = $otp->issue($user->phone, $user->phone_country_id, $user->type, OtpPurpose::REAUTHENTICATE, $user->id, $user->preferredLocale());

        return ApiResponse::success(['challenge_id' => $id, 'expires_in' => (int) config('otp.ttl_seconds'), 'resend_after' => (int) config('otp.cooldown_seconds')]);
    }

    /**
     * Confirm a sensitive operation session.
     *
     * A valid OTP grants a short recent-authentication window to this bearer token only.
     */
    public function verify(Request $request, AccountSessions $sessions, OtpService $otp): JsonResponse
    {
        $data = $request->validate(['challenge_id' => ['required', 'uuid'], 'code' => ['required', 'string', 'regex:/^[0-9]{6}$/']]);
        $user = $request->user();
        $otp->consume($data['challenge_id'], $user->phone, $user->type, OtpPurpose::REAUTHENTICATE, $user->id, $data['code'], function () use ($user, $sessions): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isActive()) {
                throw new DomainException(ErrorCode::ACCOUNT_DISABLED);
            }
            $sessions->current($user)->forceFill(['authenticated_at' => now()])->save();
        });

        return ApiResponse::success(['valid_until' => (string) now()->addMinutes((int) config('client.recent_auth_minutes'))->toIso8601String()]);
    }
}
