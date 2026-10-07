<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Enums\ErrorCode;
use App\Exceptions\DomainException;

final class GatewayManager
{
    public function get(string $provider): PaymentGatewayInterface
    {
        $settings = config('payments.providers')[$provider] ?? null;
        if (! is_array($settings) || ! is_a($settings['driver'] ?? '', PaymentGatewayInterface::class, true)) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }

        return app()->make($settings['driver'], ['settings' => $settings]);
    }
}
