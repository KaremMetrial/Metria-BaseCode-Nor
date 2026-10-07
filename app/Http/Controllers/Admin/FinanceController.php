<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\PaymentResource;
use App\Http\Resources\Shared\WalletResource;
use App\Models\Payment;
use App\Models\Wallet;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class FinanceController extends Controller
{
    /**
     * List all payments.
     *
     * Returns payments across all accounts, newest first. Provider client secrets are only exposed to the payment owner when its status permits.
     */
    public function payments(): JsonResponse
    {
        return ApiResponse::paginated(PaymentResource::class, Payment::query()->orderByDesc('id')->paginate(25));
    }

    /**
     * List all wallets.
     *
     * Returns wallets across all accounts, newest first.
     */
    public function wallets(): JsonResponse
    {
        return ApiResponse::paginated(WalletResource::class, Wallet::query()->orderByDesc('id')->paginate(25));
    }
}
