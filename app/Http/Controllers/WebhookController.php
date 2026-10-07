<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ProcessWebhook;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, ProcessWebhook $action): JsonResponse
    {
        if (strlen($request->getContent()) > 262144) {
            abort(413);
        }
        $action->execute($provider, $request->getContent(), $request->headers->all());

        return ApiResponse::success();
    }
}
