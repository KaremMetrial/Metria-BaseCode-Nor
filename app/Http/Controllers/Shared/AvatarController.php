<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Shared\Profile\UpdateAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\AvatarRequest;
use App\Http\Resources\Shared\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AvatarController extends Controller
{
    /**
     * Upload my profile image.
     *
     * Accepts a JPEG, PNG or WebP image up to 2 MB and 2048 pixels per dimension. Stores the file privately and replaces the previous image.
     */
    public function store(AvatarRequest $request, UpdateAvatar $action): JsonResponse
    {
        return ApiResponse::success(new UserResource($action->execute($request->user(), $request->file('avatar'))));
    }

    /**
     * Remove my profile image.
     *
     * Clears the image reference and removes the stored image after the transaction commits.
     */
    public function destroy(Request $request, UpdateAvatar $action): JsonResponse
    {
        return ApiResponse::success(new UserResource($action->execute($request->user(), null)));
    }
}
