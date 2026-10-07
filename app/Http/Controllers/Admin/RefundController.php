<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Payments\RefundPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RefundRequest;
use App\Models\Payment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RefundController extends Controller
{
    /**
     * Refund a payment.
     *
     * Requires an Idempotency-Key header. Supports partial refunds up to the remaining refundable amount. A pending result reserves the amount until provider confirmation; a confirmed failed refund releases that reservation. An uncertain provider outcome must be retried with the same key.
     */
    public function __invoke(RefundRequest $request, Payment $payment, RefundPayment $action): JsonResponse
    {
        $refund = $action->execute($request->user(), $payment, (int) $request->validated('amount'), $request->validated('idempotency_key'));

        return ApiResponse::success(['id' => $refund->id, 'amount' => $refund->amount, 'status' => $refund->status], __('payments.refund_processed'));
    }
}
