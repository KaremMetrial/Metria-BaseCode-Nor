<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Shared\Contact\SubmitContactMessage;
use App\Enums\ErrorCode;
use App\Enums\UserType;
use App\Exceptions\AppException;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\ContactMessageRequest;
use App\Models\Page;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class ContactMessageController extends Controller
{
    public function web(ContactMessageRequest $request, SubmitContactMessage $action): RedirectResponse
    {
        Page::query()->where('slug', 'contact-us')->where('status', 'published')->firstOrFail();
        $locale = $request->validate(['locale' => ['required', 'in:en,ar']])['locale'];
        app()->setLocale($locale);
        try {
            $message = $action->execute($request->validated(), null);
        } catch (AppException $exception) {
            throw ValidationException::withMessages($exception->errors() ?: ['message' => [$exception->userMessage()]]);
        }

        return redirect()->route('pages.show', ['slug' => 'contact-us', 'locale' => $locale])
            ->with('contact_reference', $message->reference);
    }

    /** Send a contact message.
     * Public, phone-only form. Reuse request_id and identical input after a timeout. Optional client bearer authentication associates the sender; the response exposes only a reference.
     */
    public function store(ContactMessageRequest $request, SubmitContactMessage $action): JsonResponse
    {
        $user = $request->user('sanctum');
        if ($request->bearerToken() && ! $user) {
            throw new DomainException(ErrorCode::UNAUTHENTICATED);
        }
        if ($user && ($user->type !== UserType::CLIENT || ! $user->isActive())) {
            throw new DomainException(ErrorCode::FORBIDDEN);
        }
        $message = $action->execute($request->validated(), $user);

        return ApiResponse::success(['reference' => $message->reference], __('content.contact_received'), 201);
    }
}
