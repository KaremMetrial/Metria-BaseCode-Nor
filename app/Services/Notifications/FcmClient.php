<?php

namespace App\Services\Notifications;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Models\DeviceToken;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

final class FcmClient
{
    /** False means the provider confirms that this device token is unregistered. */
    public function send(DeviceToken $device, array $payload): bool
    {
        try {
            $project = (string) config('notifications.fcm_project');
            $file = (string) config('notifications.fcm_credentials');
            if (! preg_match('/^[a-z][a-z0-9-]{4,62}$/', $project) || ! is_file($file)) {
                throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
            }
            $token = Cache::remember('fcm:oauth:'.hash('sha256', $project.'|'.$file.'|'.filemtime($file)), 120, function () use ($file): string {
                $credentials = new ServiceAccountCredentials('https://www.googleapis.com/auth/firebase.messaging', $file);
                $result = $credentials->fetchAuthToken(function (RequestInterface $request) {
                    return Http::withHeaders($request->getHeaders())->withBody((string) $request->getBody(), 'application/x-www-form-urlencoded')->connectTimeout(3)->timeout(10)->send($request->getMethod(), (string) $request->getUri())->toPsrResponse();
                });
                if (! is_string($result['access_token'] ?? null) || ($result['expires_in'] ?? 0) < 180) {
                    throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
                }

                return $result['access_token'];
            });
            $response = Http::withToken($token)->connectTimeout(3)->timeout(10)->post('https://fcm.googleapis.com/v1/projects/'.$project.'/messages:send', ['message' => ['token' => $device->token, 'notification' => ['title' => $payload['title'], 'body' => $payload['body']], 'data' => ['notification_id' => $payload['id']]]]);
            foreach ($response->json('error.details', []) as $detail) {
                if (($detail['@type'] ?? '') === 'type.googleapis.com/google.firebase.fcm.v1.FcmError' && ($detail['errorCode'] ?? '') === 'UNREGISTERED') {
                    return false;
                }
            }
            if (! $response->successful() || ! is_string($response->json('name'))) {
                throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
            }

            return true;
        } catch (\Throwable) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }
    }
}
