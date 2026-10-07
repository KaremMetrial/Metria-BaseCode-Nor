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
    public function __invoke(WalletAdjustmentRequest $request, Wallet $wallet, AdjustWallet $action): JsonResponse
    {
        return ApiResponse::success(new WalletTransactionResource($action->execute($request->user(), $wallet, $request->validated())), __('wallet.adjusted'));
    }
}
