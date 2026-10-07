<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ProcessWebhook;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WebhookController extends Controller
{
    /**
     * Receive a signed payment webhook.
     *
     * Accepts Stripe events with Stripe-Signature and MyFatoorah webhook v2 events with MyFatoorah-Signature. Maximum body size is 256 KiB. Payment and refund settlement is idempotent. MyFatoorah business events mark integration records for refresh; they never credit wallets.
     */
    public function __invoke(Request $request, string $provider, ProcessWebhook $action): JsonResponse
    {
        if (strlen($request->getContent()) > 262144) {
            abort(413);
        }
        $action->execute($provider, $request->getContent(), $request->headers->all());

        return ApiResponse::success();
    }
}
