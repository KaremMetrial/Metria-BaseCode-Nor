<?php
namespace App\Http\Controllers\Auth;
use App\Actions\Auth\Logout;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\{Request,JsonResponse};
final class LogoutController extends Controller {
 public function __invoke(Request $request,Logout $action): JsonResponse { $action->execute($request->user()); return ApiResponse::success(null,__('auth.logout')); }
}
