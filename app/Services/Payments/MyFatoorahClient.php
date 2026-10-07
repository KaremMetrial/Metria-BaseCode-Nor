<?php

namespace App\Services\Payments;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class MyFatoorahClient
{
    public function __construct(private readonly ?array $settings = null) {}

    public function setting(string $key): mixed
    {
        return ($this->settings ?? config('payments.providers.myfatoorah'))[$key] ?? null;
    }

    public function baseUrl(): string
    {
        $host = match ($this->setting('country')) {
            'KWT', 'BHR', 'JOR', 'OMN' => 'api.myfatoorah.com',
            'SAU' => 'api-sa.myfatoorah.com',
            'ARE' => 'api-ae.myfatoorah.com',
            'QAT' => 'api-qa.myfatoorah.com',
            'EGY' => 'api-eg.myfatoorah.com',
            default => throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE),
        };

        return 'https://'.($this->setting('livemode') ? $host : 'apitest.myfatoorah.com');
    }

    /** @return array<string|int, mixed> */
    public function request(string $method, string $path, array $payload = [], array $query = [], ?string $key = null): array
    {
        if (! is_string($this->setting('secret')) || $this->setting('secret') === '') {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }
        $request = Http::withToken($this->setting('secret'))->acceptJson()->asJson()
            ->connectTimeout(3)->timeout(20)->withoutRedirecting();
        if ($key !== null) {
            $request = $request->withHeaders(['Idempotency-Key' => $key]);
        }
        try {
            $response = $request->send($method, $this->baseUrl().$path, array_filter([
                'query' => $query,
                'json' => $payload === [] ? null : $payload,
            ], fn ($value) => $value !== null));
        } catch (ConnectionException) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }
        $data = $response->json();
        if (! $response->successful() || ! is_array($data)) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }
        if (in_array($path, ['/v2/GetSuppliers', '/v2/GetBanks', '/v2/GetCurrenciesExchangeList'], true) && array_is_list($data)) {
            return ['items' => $data];
        }
        if ($path === '/v2/GetSupplierDetails' && isset($data['SupplierCode'])) {
            return $data;
        }
        if (($data['IsSuccess'] ?? null) !== true) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }

        $result = $data['Data'] ?? null;

        return is_array($result) ? (array_is_list($result) ? ['items' => $result] : $result) : ['value' => $result, 'message' => $data['Message'] ?? null];
    }
}
