<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\WalletResource;
use App\Http\Resources\Shared\WalletTransactionResource;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WalletController extends Controller
{
    /**
     * List my wallets.
     *
     * Returns an unpaginated array of wallets ordered by currency. Balances are integers in minor units.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(WalletResource::collection(Wallet::query()->where('user_id', $request->user()->id)->orderBy('currency')->get()));
    }

    /**
     * List wallet transactions.
     *
     * Returns transactions for an owned wallet, newest first. Includes the balance after each transaction and the ledger reason.
     */
    public function history(Request $request, int $wallet): JsonResponse
    {
        $owned = Wallet::query()->where('user_id', $request->user()->id)->findOrFail($wallet);

        return ApiResponse::paginated(WalletTransactionResource::class, WalletTransaction::query()->where('wallet_id', $owned->id)->orderByDesc('id')->paginate(25));
    }
}
