<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Contracts\Payments\ReconcilesPayments;
use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Exceptions\RefundRejectedException;
use App\Models\Payment;
use App\Models\PaymentRefund;
use Illuminate\Support\Facades\Http;

final class StripeGateway implements PaymentGatewayInterface, ReconcilesPayments
{
    public function __construct(private readonly array $settings) {}

    public function create(Payment $payment): array
    {
        $data = $this->post('payment_intents', ['amount' => $payment->amount, 'currency' => strtolower($payment->currency), 'metadata' => ['payment_uuid' => $payment->uuid], 'automatic_payment_methods' => ['enabled' => 'true']], 'payment:'.$payment->uuid);
        if (! is_string($data['id'] ?? null) || ! is_string($data['client_secret'] ?? null) || ($data['amount'] ?? null) !== $payment->amount || strtoupper($data['currency'] ?? '') !== $payment->currency) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }

        return ['reference' => $data['id'], 'client_secret' => $data['client_secret']];
    }

    public function refund(Payment $payment, PaymentRefund $refund): array
    {
        $data = $this->post('refunds', ['payment_intent' => $payment->provider_reference, 'amount' => $refund->amount, 'metadata' => ['refund_uuid' => $refund->uuid]], 'refund:'.$refund->uuid);
        if (! is_string($data['id'] ?? null) || ! in_array($data['status'] ?? '', ['succeeded', 'pending', 'failed', 'canceled', 'requires_action'], true)) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }

        return ['reference' => $data['id'], 'status' => match ($data['status']) {
            'succeeded' => 'succeeded','failed','canceled' => 'failed',default => 'pending'
        }];
    }

    private function post(string $path, array $payload, string $key): array
    {
        if (empty($this->settings['secret'])) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }
        try {
            $response = Http::asForm()->withToken($this->settings['secret'])->withHeaders(['Idempotency-Key' => $key])->connectTimeout(3)->timeout(15)->post('https://api.stripe.com/v1/'.$path, $payload);
        } catch (\Throwable) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }
        if ($path === 'refunds' && $response->status() === 400
            && $response->json('error.type') === 'invalid_request_error'
            && $response->json('error.code') !== 'idempotency_key_in_use'
            && strtolower($response->header('Stripe-Should-Retry')) !== 'true') {
            throw new RefundRejectedException('REFUND_REJECTED');
        }
        if (! $response->successful() || ! is_array($response->json())) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }

        return $response->json();
    }

    public function verifyWebhook(string $body, array $headers): array
    {
        $signature = (string) ($headers['stripe-signature'][0] ?? '');
        $secret = $this->settings['webhook_secret'] ?? '';
        if ($secret === '') {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }
        $parts = [];
        foreach (explode(',', $signature) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]][] = $pair[1];
            }
        }
        $timestamp = $parts['t'][0] ?? '';
        if (! ctype_digit($timestamp) || abs(now()->timestamp - (int) $timestamp) > 300) {
            throw new DomainException(ErrorCode::INVALID_WEBHOOK);
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        $valid = false;
        foreach ($parts['v1'] ?? [] as $value) {
            if (hash_equals($expected, $value)) {
                $valid = true;
            }
        }
        if (! $valid) {
            throw new DomainException(ErrorCode::INVALID_WEBHOOK);
        }
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new DomainException(ErrorCode::INVALID_WEBHOOK);
        }
        if (! is_array($data) || ! is_string($data['id'] ?? null) || ! is_string($data['type'] ?? null) || ! is_array($data['data']['object'] ?? null) || ($data['livemode'] ?? null) !== ($this->settings['livemode'] ?? true)) {
            throw new DomainException(ErrorCode::INVALID_WEBHOOK);
        }

        return $this->normalize($data);
    }

    private function normalize(array $data): array
    {
        $object = $data['data']['object'];

        return [
            'id' => $data['id'],
            'type' => match ($data['type']) {
                'payment_intent.succeeded' => 'payment.succeeded','payment_intent.canceled' => 'payment.canceled','refund.created','refund.updated','refund.failed' => 'refund.updated',default => 'ignored'
            },
            'reference' => $object['id'] ?? null,
            'payment_uuid' => $object['metadata']['payment_uuid'] ?? null,
            'refund_uuid' => $object['metadata']['refund_uuid'] ?? null,
            'payment_reference' => $object['payment_intent'] ?? null,
            'amount' => $object['amount'] ?? null,
            'received_amount' => $object['amount_received'] ?? null,
            'currency' => strtoupper($object['currency'] ?? ''),
            'status' => match ($object['status'] ?? '') {
                'succeeded' => 'succeeded','canceled' => 'canceled','failed' => 'failed',default => 'pending'
            },
        ];
    }

    public function lookupPayment(Payment $payment): ?array
    {
        if ($payment->provider_reference !== null) {
            $object = $this->get('payment_intents/'.rawurlencode($payment->provider_reference));
        } else {
            $result = $this->get('payment_intents/search', ['query' => "metadata['payment_uuid']:'".$payment->uuid."'", 'limit' => 2]);
            if (! is_array($result['data'] ?? null) || ($result['has_more'] ?? true) || count($result['data']) > 1) {
                throw new DomainException(ErrorCode::RECONCILIATION_REQUIRED);
            }
            $object = $result['data'][0] ?? null;
            if ($object === null) {
                return null;
            }
        }
        $this->validateSnapshot($object);
        if (($object['metadata']['payment_uuid'] ?? null) !== $payment->uuid || ($object['amount'] ?? null) !== $payment->amount || strtoupper($object['currency'] ?? '') !== $payment->currency) {
            throw new DomainException(ErrorCode::INVALID_WEBHOOK);
        }

        return $this->snapshotEvent($object, match ($object['status'] ?? '') {
            'succeeded' => 'payment_intent.succeeded',
            'canceled' => 'payment_intent.canceled',
            default => 'payment.pending',
        });
    }

    public function lookupRefund(Payment $payment, PaymentRefund $refund): ?array
    {
        if ($refund->provider_reference !== null) {
            $object = $this->get('refunds/'.rawurlencode($refund->provider_reference));
        } else {
            $parameters = ['payment_intent' => $payment->provider_reference, 'limit' => 100];
            $object = null;
            for ($page = 0; $page < 10; $page++) {
                $result = $this->get('refunds', $parameters);
                if (! is_array($result['data'] ?? null)) {
                    throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
                }
                foreach ($result['data'] as $candidate) {
                    if (($candidate['metadata']['refund_uuid'] ?? null) === $refund->uuid) {
                        if ($object !== null) {
                            throw new DomainException(ErrorCode::RECONCILIATION_REQUIRED);
                        }
                        $object = $candidate;
                    }
                }
                if (($result['has_more'] ?? true) === false) {
                    break;
                }
                $last = end($result['data']);
                if (! is_array($last) || ! is_string($last['id'] ?? null) || $page === 9) {
                    throw new DomainException(ErrorCode::RECONCILIATION_REQUIRED);
                }
                $parameters['starting_after'] = $last['id'];
            }
            if ($object === null) {
                return null;
            }
        }
        $this->validateSnapshot($object);
        if (($object['metadata']['refund_uuid'] ?? null) !== $refund->uuid || ($object['payment_intent'] ?? null) !== $payment->provider_reference || ($object['amount'] ?? null) !== $refund->amount || strtoupper($object['currency'] ?? '') !== $payment->currency) {
            throw new DomainException(ErrorCode::INVALID_WEBHOOK);
        }

        return $this->snapshotEvent($object, 'refund.updated');
    }

    private function validateSnapshot(array $object): void
    {
        if (! is_string($object['id'] ?? null) || ($object['livemode'] ?? null) !== ($this->settings['livemode'] ?? true)) {
            throw new DomainException(ErrorCode::INVALID_WEBHOOK);
        }
    }

    private function snapshotEvent(array $object, string $type): array
    {
        $event = $this->normalize(['id' => '', 'type' => $type, 'data' => ['object' => $object]]);
        $event['id'] = 'reconcile:'.hash('sha256', json_encode($event, JSON_THROW_ON_ERROR));

        return $event;
    }

    private function get(string $path, array $parameters = []): array
    {
        if (empty($this->settings['secret'])) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }
        try {
            $response = Http::withToken($this->settings['secret'])->connectTimeout(3)->timeout(15)->get('https://api.stripe.com/v1/'.$path, $parameters);
            if (! $response->successful() || ! is_array($response->json())) {
                throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
            }

            return $response->json();
        } catch (\Throwable) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }
    }
}
