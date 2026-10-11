<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\ListWalletsRequest;
use App\Http\Requests\Shared\ListWalletTransactionsRequest;
use App\Http\Resources\Shared\WalletResource;
use App\Http\Resources\Shared\WalletTransactionResource;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;

final class WalletController extends Controller
{
    /**
     * List my wallets.
     *
     * Returns an unpaginated array of wallets ordered by currency. Balances are integers in minor units.
     */
    public function index(ListWalletsRequest $request, ListQuery $filters): JsonResponse
    {
        return ApiResponse::success(WalletResource::collection($filters->apply(Wallet::query()->where('user_id', $request->user()->id), $request)->get()));
    }

    /**
     * List wallet transactions.
     *
     * Returns transactions for an owned wallet, newest first. Includes the balance after each transaction and the ledger reason.
     */
    public function history(ListWalletTransactionsRequest $request, int $wallet, ListQuery $filters): JsonResponse
    {
        $owned = Wallet::query()->where('user_id', $request->user()->id)->findOrFail($wallet);

        return ApiResponse::paginated(WalletTransactionResource::class, $filters->apply(WalletTransaction::query()->where('wallet_id', $owned->id), $request)->paginate($request->perPage()));
    }
}
