<?php
namespace App\Http\Controllers\Shared;
use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\{WalletResource,WalletTransactionResource};
use App\Models\{Wallet,WalletTransaction};
use App\Services\Wallet\WalletService;
use App\Support\ApiResponse;
use Illuminate\Http\{Request,JsonResponse};
final class WalletController extends Controller {
 public function index(Request $request): JsonResponse { return ApiResponse::success(WalletResource::collection(Wallet::query()->where('user_id',$request->user()->id)->orderBy('currency')->get())); }
 public function history(Request $request,int $wallet): JsonResponse {
  $owned=Wallet::query()->where('user_id',$request->user()->id)->findOrFail($wallet);
  return ApiResponse::paginated(WalletTransactionResource::class,WalletTransaction::query()->where('wallet_id',$owned->id)->orderByDesc('id')->paginate(25));
 }
}
