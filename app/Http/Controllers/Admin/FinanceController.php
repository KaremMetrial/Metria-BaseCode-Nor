<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListPaymentsRequest;
use App\Http\Requests\Admin\ListWalletsRequest;
use App\Http\Resources\Shared\PaymentResource;
use App\Http\Resources\Shared\WalletResource;
use App\Models\Payment;
use App\Models\Wallet;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;

final class FinanceController extends Controller
{
    /**
     * List all payments.
     *
     * Returns payments across all accounts, newest first. Provider client secrets are only exposed to the payment owner when its status permits.
     */
    public function payments(ListPaymentsRequest $request, ListQuery $filters): JsonResponse
    {
        return ApiResponse::paginated(PaymentResource::class, $filters->apply(Payment::query(), $request)->paginate($request->perPage()));
    }

    /**
     * List all wallets.
     *
     * Returns wallets across all accounts, newest first.
     */
    public function wallets(ListWalletsRequest $request, ListQuery $filters): JsonResponse
    {
        return ApiResponse::paginated(WalletResource::class, $filters->apply(Wallet::query(), $request)->paginate($request->perPage()));
    }
}
