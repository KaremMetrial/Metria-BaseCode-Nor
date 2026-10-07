<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Payments\CreatePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\PaymentRequest;
use App\Http\Resources\Shared\PaymentResource;
use App\Models\Payment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(PaymentResource::class, Payment::query()->where('user_id', $request->user()->id)->orderByDesc('id')->paginate(25));
    }

    public function show(Request $request, int $payment): JsonResponse
    {
        return ApiResponse::success(new PaymentResource(Payment::query()->where('user_id', $request->user()->id)->findOrFail($payment)));
    }

    public function store(PaymentRequest $request, CreatePayment $action): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success(new PaymentResource($action->execute($request->user(), (int) $data['amount'], $data['currency'], $data['provider'], $data['idempotency_key'])), __('payments.created'), 201);
    }
}
