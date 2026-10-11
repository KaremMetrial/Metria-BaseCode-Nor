<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Shared\Support\ManageConversation;
use App\Http\Controllers\Shared\SupportConversationController as SharedSupportConversationController;
use App\Http\Resources\Shared\SupportConversationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SupportConversationController extends SharedSupportConversationController
{
    /** Assign a support conversation.
     * Send version and assigned_to (an active staff member with support.read and support.reply), or null to return it to the shared inbox.
     */
    public function assignment(Request $request, int $conversation, ManageConversation $action): JsonResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'assigned_to' => ['present', 'nullable', 'integer']]);

        return ApiResponse::success(new SupportConversationResource($action->assign($request->user(), $conversation, isset($data['assigned_to']) ? (int) $data['assigned_to'] : null, (int) $data['version'])));
    }

    /** Update support status.
     * Requires support.manage and the current version. Staff can resolve, close or reopen a conversation.
     */
    public function status(Request $request, int $conversation, ManageConversation $action): JsonResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'status' => ['required', Rule::in(['new', 'in_progress', 'resolved', 'closed'])]]);

        return ApiResponse::success(new SupportConversationResource($action->status($request->user(), $conversation, $data['status'], (int) $data['version'])));
    }
}
