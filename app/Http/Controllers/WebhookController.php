<?php
namespace App\Http\Controllers;
use App\Actions\Payments\ProcessWebhook;
use App\Support\ApiResponse;
use Illuminate\Http\{Request,JsonResponse};
final class WebhookController extends Controller {
 public function __invoke(Request $request,string $provider,ProcessWebhook $action): JsonResponse {
  if(strlen($request->getContent())>262144) { abort(413); }
  $action->execute($provider,$request->getContent(),(string)$request->header('Stripe-Signature'));
  return ApiResponse::success();
 }
}
