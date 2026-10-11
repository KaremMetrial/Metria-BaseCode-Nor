<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Shared\Support\ManageConversation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\SupportMessageRequest;
use App\Http\Resources\Shared\SupportConversationResource;
use App\Http\Resources\Shared\SupportMessageResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

abstract class SupportConversationController extends Controller
{
    /** List support conversations.
     * Filter by status; returns unread counts and read markers. Clients see only their own conversations; authorized staff see the support inbox.
     */
    public function index(Request $request, ManageConversation $action): JsonResponse
    {
        $data = $request->validate(['status' => ['sometimes', Rule::in(['new', 'in_progress', 'resolved', 'closed'])]]);
        $query = $action->query($request->user())->orderByDesc('updated_at')->orderByDesc('id');
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return ApiResponse::paginated(SupportConversationResource::class, $query->paginate(25));
    }

    /** Read a support conversation.
     * Returns status, optimistic version, last_message_id and per-side read-through markers, without marking messages read automatically.
     */
    public function show(Request $request, int $conversation, ManageConversation $action): JsonResponse
    {
        return ApiResponse::success(new SupportConversationResource($action->query($request->user())->findOrFail($conversation)));
    }

    /** Retrieve support messages.
     * Ordered by ID ascending. Send after_id from next_after_id until has_more is false; reconnect using the last persisted message ID. Message bodies are plain text.
     */
    public function messages(Request $request, int $conversation, ManageConversation $action): JsonResponse
    {
        $data = $request->validate(['after_id' => ['sometimes', 'integer', 'min:0'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        $row = $action->query($request->user())->findOrFail($conversation);
        $limit = (int) ($data['per_page'] ?? 50);
        $items = $row->messages()->with('attachments')->where('id', '>', $data['after_id'] ?? 0)->orderBy('id')->limit($limit + 1)->get();
        $more = $items->count() > $limit;
        $items = $items->take($limit);

        return ApiResponse::success(['items' => SupportMessageResource::collection($items), 'meta' => ['has_more' => $more, 'next_after_id' => $items->last()->id ?? (int) ($data['after_id'] ?? 0)]]);
    }

    /** Send a support message.
     * Reuse client_message_id with identical text after a timeout. Closed conversations reject new messages; replying to a resolved conversation reopens it.
     */
    public function send(SupportMessageRequest $request, int $conversation, ManageConversation $action): JsonResponse
    {
        return ApiResponse::success(new SupportMessageResource($action->send($request->user(), $conversation, $request->validated())), status: 201);
    }

    /** Mark messages as read.
     * Send through_message_id from a displayed message. The marker only advances and cannot refer to another conversation.
     */
    public function read(Request $request, int $conversation, ManageConversation $action): JsonResponse
    {
        $data = $request->validate(['through_message_id' => ['required', 'integer', 'min:0']]);

        return ApiResponse::success(new SupportConversationResource($action->read($request->user(), $conversation, (int) $data['through_message_id'])));
    }
}
