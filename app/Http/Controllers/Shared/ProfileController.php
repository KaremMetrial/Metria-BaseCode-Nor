<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Profile\UpdateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\Shared\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    /**
     * Get my profile.
     *
     * Available to pending accounts as well as active accounts.
     */
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()));
    }

    /**
     * Update my profile.
     *
     * Updates only the supplied name, preferred locale and timezone. Use the separate phone verification flow to change a phone number.
     */
    public function update(UpdateProfileRequest $request, UpdateProfile $action): JsonResponse
    {
        return ApiResponse::success(new UserResource($action->execute($request->user(), $request->validated())), __('auth.profile_updated'));
    }
}
