<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Access\AssignUserRole;
use App\Actions\Access\ChangeUserStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRoleRequest;
use App\Http\Requests\Admin\UserStatusRequest;
use App\Http\Resources\Shared\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UserManagementController extends Controller
{
    /**
     * List users.
     *
     * Returns users across actor types, newest first.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::paginated(UserResource::class, User::query()->orderByDesc('id')->paginate(25));
    }

    /**
     * Change a user’s status.
     *
     * Requires vendors.approve for vendor approval, vendors.reject for rejecting a pending vendor, or users.block for other state changes. Administrators cannot change their own status or another administrator’s status.
     */
    public function status(UserStatusRequest $request, User $user, ChangeUserStatus $action): JsonResponse
    {
        return ApiResponse::success(new UserResource($action->execute($request->user(), $user, UserStatus::from($request->validated('status')))));
    }

    /**
     * Assign an administrator role.
     *
     * Requires the super-admin role. The target must be an administrator. Adds the role without removing existing roles; assigning the same role again is idempotent.
     */
    public function role(UserRoleRequest $request, User $user, AssignUserRole $action): JsonResponse
    {
        $action->execute($request->user(), $user, $request->validated('role'));

        return ApiResponse::success();
    }
}
