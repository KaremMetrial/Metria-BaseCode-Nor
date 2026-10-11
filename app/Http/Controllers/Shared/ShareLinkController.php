<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\ShareLinkResource;
use App\Services\Links\ShareLinks;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShareLinkController extends Controller
{
    /**
     * Resolve a public app share link.
     *
     * Resolve on both cold and warm app launch. Preserve target and referral_code through OTP registration.
     * Resolution never logs in a user, claims a referral or grants points. Hidden, revoked and deleted targets return 404.
     */
    public function show(string $code, ShareLinks $links): JsonResponse
    {
        return ApiResponse::success(new ShareLinkResource($links->find($code)))->header('Cache-Control', 'no-store');
    }

    public function web(Request $request, string $code, ShareLinks $links): Response
    {
        $status = 200;
        try {
            $link = $links->find($code);
            $data = (new ShareLinkResource($link))->resolve($request);
        } catch (ModelNotFoundException|NotFoundHttpException) {
            $data = null;
            $status = 404;
        }

        return response()->view('links.show', ['link' => $data], $status)->withHeaders([
            'Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
