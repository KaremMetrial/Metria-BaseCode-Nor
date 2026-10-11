<?php

namespace App\Http\Controllers\Client;

use App\Actions\Shared\Support\ManageConversation;
use App\Http\Controllers\Shared\SupportConversationController as SharedSupportConversationController;
use App\Http\Requests\Client\SupportConversationRequest;
use App\Http\Resources\Shared\SupportConversationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SupportConversationController extends SharedSupportConversationController
{
    /** Start a support conversation.
     * Supply subject, first message body, request_id and client_message_id UUIDs. Identical retries return the existing conversation; at most five unresolved conversations are allowed.
     */
    public function store(SupportConversationRequest $request, ManageConversation $action): JsonResponse
    {
        return ApiResponse::success(new SupportConversationResource($action->create($request->user(), $request->validated())), status: 201);
    }

    /** Close my conversation.
     * Send the current conversation version. Clients can close their conversation; reopening a closed conversation is a staff operation.
     */
    public function close(Request $request, int $conversation, ManageConversation $action): JsonResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        return ApiResponse::success(new SupportConversationResource($action->status($request->user(), $conversation, 'closed', (int) $data['version'])));
    }
}
