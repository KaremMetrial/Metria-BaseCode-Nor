<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ContactMessageResource;
use App\Models\ContactMessage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ContactMessageController extends Controller
{
    /** List contact submissions.
     * Filter by new, reviewed or closed; search by reference, subject or phone. Newest first, 25 per page.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['sometimes', Rule::in(['new', 'reviewed', 'closed'])], 'search' => ['sometimes', 'string', 'max:100']]);
        $query = ContactMessage::query()->orderByDesc('id');
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (isset($data['search'])) {
            $query->where(fn ($q) => $q->where('reference', $data['search'])->orWhere('subject', 'like', '%'.$data['search'].'%')->orWhere('phone', $data['search']));
        }

        return ApiResponse::paginated(ContactMessageResource::class, $query->paginate(25));
    }

    /** Read a contact submission.
     * Restricted to staff with contact.read; does not create a support conversation or disclose data publicly.
     */
    public function show(ContactMessage $message): JsonResponse
    {
        return ApiResponse::success(new ContactMessageResource($message));
    }

    /** Update contact handling status.
     * Set new, reviewed or closed. The staff action is audited without storing message text in the audit log.
     */
    public function status(Request $request, ContactMessage $message, AuditLoggerInterface $audit): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['new', 'reviewed', 'closed'])]]);

        return DB::transaction(function () use ($request, $message, $data, $audit): JsonResponse {
            $locked = ContactMessage::query()->lockForUpdate()->findOrFail($message->id);
            $locked->forceFill(['status' => $data['status']])->save();
            $audit->record(new AuditEntry(AuditAction::CONTACT_REVIEWED, $locked, actor: $request->user(), context: ['status' => $data['status']]));

            return ApiResponse::success(new ContactMessageResource($locked));
        }, 5);
    }
}
