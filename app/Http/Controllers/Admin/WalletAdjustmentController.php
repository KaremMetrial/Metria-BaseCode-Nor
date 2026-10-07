<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Wallet\AdjustWallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\WalletAdjustmentRequest;
use App\Http\Resources\Shared\WalletTransactionResource;
use App\Models\Wallet;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class WalletAdjustmentController extends Controller
{
    /**
     * Adjust a wallet balance.
     *
     * Requires wallets.adjust and wallets.credit or wallets.debit according to direction. Include a reason and an Idempotency-Key header. The ledger is append-only; locked wallets and insufficient balances reject the operation.
     */
    public function __invoke(WalletAdjustmentRequest $request, Wallet $wallet, AdjustWallet $action): JsonResponse
    {
        return ApiResponse::success(new WalletTransactionResource($action->execute($request->user(), $wallet, $request->validated())), __('wallet.adjusted'));
    }
}
