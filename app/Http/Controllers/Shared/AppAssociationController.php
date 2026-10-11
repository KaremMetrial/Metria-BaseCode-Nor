<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class AppAssociationController extends Controller
{
    public function android(): JsonResponse
    {
        $package = config('links.android_package');
        $fingerprints = config('links.android_fingerprints', []);
        abort_unless(is_string($package) && preg_match('/^[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z][A-Za-z0-9_]*)+$/', $package) && count($fingerprints) > 0, 404);
        foreach ($fingerprints as $fingerprint) {
            abort_unless(is_string($fingerprint) && preg_match('/^(?:[A-Fa-f0-9]{2}:){31}[A-Fa-f0-9]{2}$/', $fingerprint), 404);
        }

        return response()->json([['relation' => ['delegate_permission/common.handle_all_urls'], 'target' => [
            'namespace' => 'android_app', 'package_name' => $package, 'sha256_cert_fingerprints' => $fingerprints,
        ]]])->header('Cache-Control', 'public, max-age=300');
    }

    public function apple(): JsonResponse
    {
        $appId = config('links.ios_app_id');
        abort_unless(is_string($appId) && preg_match('/^[A-Z0-9]{10}\.[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+$/', $appId), 404);

        return response()->json(['applinks' => ['apps' => [], 'details' => [['appID' => $appId, 'paths' => ['/l/*']]]]])
            ->header('Cache-Control', 'public, max-age=300');
    }
}
