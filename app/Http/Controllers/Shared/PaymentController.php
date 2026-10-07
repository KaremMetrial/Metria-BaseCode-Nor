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
    /**
     * List my payments.
     *
     * Returns only payments owned by the caller, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(PaymentResource::class, Payment::query()->where('user_id', $request->user()->id)->orderByDesc('id')->paginate(25));
    }

    /**
     * Get a payment.
     *
     * Returns an owned payment. The client_secret field is present only for the owner while payment status is pending or processing.
     */
    public function show(Request $request, int $payment): JsonResponse
    {
        return ApiResponse::success(new PaymentResource(Payment::query()->where('user_id', $request->user()->id)->findOrFail($payment)));
    }

    /**
     * Create a payment.
     *
     * Creates or replays an idempotent payment intent. Supply Idempotency-Key in the header. Provider defaults to the configured gateway; payment processing must be configured by the operator. For Stripe, confirm client_secret using its SDK. For MyFatoorah, redirect to payment_url. Wait for a verified webhook or reconciliation to settle the payment.
     */
    public function store(PaymentRequest $request, CreatePayment $action): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success(new PaymentResource($action->execute($request->user(), (int) $data['amount'], $data['currency'], $data['provider'], $data['idempotency_key'])), __('payments.created'), 201);
    }
}
