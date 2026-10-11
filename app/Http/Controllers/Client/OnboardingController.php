<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\Auth\CompleteProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\CompleteProfileRequest;
use App\Http\Resources\Shared\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class OnboardingController extends Controller
{
    /**
     * Complete client registration.
     *
     * Stores the profile and consent to the current terms version. Repeated submissions preserve the original completion timestamp.
     */
    public function __invoke(CompleteProfileRequest $request, CompleteProfile $action): JsonResponse
    {
        return ApiResponse::success(['user' => new UserResource($action->execute($request->user(), $request->validated())), 'next_step' => 'home']);
    }
}
