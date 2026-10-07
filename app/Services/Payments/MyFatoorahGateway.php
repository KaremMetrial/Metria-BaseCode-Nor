<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Contracts\Payments\ReconcilesPayments;
use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Support\Money;

final class MyFatoorahGateway implements PaymentGatewayInterface, ReconcilesPayments
{
    private readonly MyFatoorahClient $client;

    public function __construct(private readonly array $settings)
    {
        $this->client = new MyFatoorahClient($settings);
    }

    public function create(Payment $payment): array
    {
        if ($payment->currency !== ($this->settings['currency'] ?? null)) {
            throw new DomainException(ErrorCode::CURRENCY_MISMATCH);
        }
        $payload = [
            'Order' => ['Amount' => (float) Money::decimal($payment->amount, $payment->currency), 'Currency' => $payment->currency, 'ExternalIdentifier' => $payment->uuid],
            'NotificationOption' => 'LINK',
            'OperationType' => 'PAY',
            'Language' => app()->getLocale() === 'ar' ? 'AR' : 'EN',
        ];
        if (! empty($this->settings['redirect_url'])) {
            $payload['IntegrationUrls']['Redirection'] = $this->settings['redirect_url'];
        }
        $data = $this->client->request('POST', '/v3/payments', $payload, key: 'payment:'.$payment->uuid);
        $reference = $this->reference($data['InvoiceId'] ?? null);
        $url = $data['PaymentURL'] ?? null;
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }

        return ['reference' => $reference, 'payment_url' => $url];
    }

    public function refund(Payment $payment, PaymentRefund $refund): array
    {
        $invoice = $this->invoice($payment);
        $event = $this->paymentEvent($invoice);
        if ($event === null || $event['payment_uuid'] !== $payment->uuid || $event['amount'] !== $payment->amount || $event['currency'] !== $payment->currency) {
            throw new DomainException(ErrorCode::RECONCILIATION_REQUIRED);
        }
        $successful = array_values(array_filter($invoice['Transactions'] ?? [], fn (array $transaction) => ($transaction['Status'] ?? null) === 'SUCCESS'));
        if (count($successful) !== 1) {
            throw new DomainException(ErrorCode::RECONCILIATION_REQUIRED);
        }
        $data = $this->client->request('POST', '/v3/refunds', [
            'PaymentId' => $this->reference($successful[0]['PaymentId'] ?? null),
            'Amount' => (float) Money::decimal($refund->amount, $payment->currency),
            'ExternalIdentifier' => $refund->uuid,
            'ServiceChargeOnCustomer' => false,
        ], key: 'refund:'.$refund->uuid);
        $event = $this->refundEvent($data);
        $this->validateRefund($event, $payment, $refund);

        return ['reference' => $event['reference'], 'status' => $event['status']];
    }

    public function lookupPayment(Payment $payment): ?array
    {
        return $this->paymentEvent($this->invoice($payment));
    }

    public function lookupRefund(Payment $payment, PaymentRefund $refund): ?array
    {
        $reference = $refund->provider_reference;
        if ($reference === null) {
            $results = $this->client->request('POST', '/v2/GetRefundStatus', ['Key' => $payment->provider_reference, 'KeyType' => 'InvoiceId']);
            $matches = array_values(array_filter($results['RefundStatusResult'] ?? [], fn (array $item) => ($item['ExternalIdentifier'] ?? null) === $refund->uuid));
            if ($matches === []) {
                return null;
            }
            if (count($matches) !== 1) {
                throw new DomainException(ErrorCode::RECONCILIATION_REQUIRED);
            }
            $reference = $this->reference($matches[0]['RefundId'] ?? null);
        }
        $event = $this->refundEvent($this->client->request('GET', '/v3/refunds/'.rawurlencode($reference)));
        $this->validateRefund($event, $payment, $refund);

        return $event;
    }

    public function verifyWebhook(string $body, array $headers): array
    {
        $verified = (new MyFatoorahSignature)->verify($body, $headers, (string) ($this->settings['webhook_secret'] ?? ''));
        $data = $verified['data'];
        $event = null;
        if ($verified['code'] === 1) {
            $invoiceId = $this->reference(data_get($data, 'Invoice.Id'));
            $paymentId = $this->reference(data_get($data, 'Transaction.PaymentId'));
            $snapshot = $this->client->request('GET', '/v3/payments/'.rawurlencode($paymentId));
            if ((string) data_get($snapshot, 'Invoice.Id') !== $invoiceId || (string) data_get($snapshot, 'Transaction.PaymentId') !== $paymentId) {
                throw new DomainException(ErrorCode::INVALID_WEBHOOK);
            }
            $event = $this->paymentEvent($snapshot);
            if ($event !== null && ! Payment::query()->where('provider', 'myfatoorah')->where('uuid', $event['payment_uuid'])->exists()) {
                $event = null;
            }
        } elseif ($verified['code'] === 2) {
            $reference = $this->reference(data_get($data, 'Refund.Id'));
            $snapshot = $this->client->request('GET', '/v3/refunds/'.rawurlencode($reference));
            if ((string) data_get($snapshot, 'Refund.Id') !== $reference || (string) data_get($snapshot, 'ReferencedInvoice.Id') !== (string) data_get($data, 'ReferencedInvoice.Id')) {
                throw new DomainException(ErrorCode::INVALID_WEBHOOK);
            }
            $event = $this->refundEvent($snapshot);
            if (! PaymentRefund::query()->where('uuid', $event['refund_uuid'])->exists()) {
                $event = null;
            }
        }
        if ($event !== null) {
            $event['id'] = 'mf:wh:'.hash('sha256', $body.json_encode($event, JSON_THROW_ON_ERROR));

            return $event;
        }

        return ['id' => 'mf:wh:'.hash('sha256', $body), 'type' => 'integration.updated', 'code' => $verified['code'], 'data' => $data];
    }

    private function invoice(Payment $payment): array
    {
        $path = $payment->provider_reference !== null
            ? '/v3/invoices/'.rawurlencode($payment->provider_reference)
            : '/v3/invoices/externalIdentifier/'.rawurlencode($payment->uuid);

        return $this->client->request('GET', $path);
    }

    private function paymentEvent(array $data): ?array
    {
        $transactions = $data['Transactions'] ?? [$data['Transaction'] ?? []];
        $success = array_filter($transactions, fn (array $item) => ($item['Status'] ?? null) === 'SUCCESS');
        if (data_get($data, 'Invoice.Status') !== 'PAID' || $success === []) {
            return null;
        }
        $currency = (string) data_get($data, 'Amount.BaseCurrency');
        $amount = Money::minor(data_get($data, 'Amount.ValueInBaseCurrency'), $currency);
        $event = [
            'type' => 'payment.succeeded', 'reference' => $this->reference(data_get($data, 'Invoice.Id')),
            'payment_uuid' => (string) data_get($data, 'Invoice.ExternalIdentifier'),
            'amount' => $amount, 'received_amount' => $amount, 'currency' => $currency, 'status' => 'succeeded',
        ];

        return $this->identified($event);
    }

    private function refundEvent(array $data): array
    {
        $currency = (string) data_get($data, 'Amount.BaseCurrency');
        $event = [
            'type' => 'refund.updated', 'reference' => $this->reference(data_get($data, 'Refund.Id')),
            'refund_uuid' => (string) data_get($data, 'Refund.ExternalIdentifier'),
            'payment_reference' => $this->reference(data_get($data, 'ReferencedInvoice.Id')),
            'amount' => Money::minor(data_get($data, 'Amount.ValueInBaseCurrency'), $currency), 'currency' => $currency,
            'status' => match (strtoupper((string) data_get($data, 'Refund.Status'))) {
                'REFUNDED' => 'succeeded', 'CANCELED' => 'failed', 'PENDING' => 'pending',
                default => throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE),
            },
        ];

        return $this->identified($event);
    }

    private function validateRefund(array $event, Payment $payment, PaymentRefund $refund): void
    {
        if ($event['refund_uuid'] !== $refund->uuid || $event['payment_reference'] !== $payment->provider_reference || $event['amount'] !== $refund->amount || $event['currency'] !== $payment->currency) {
            throw new DomainException(ErrorCode::RECONCILIATION_REQUIRED);
        }
    }

    private function identified(array $event): array
    {
        return ['id' => 'mf:'.hash('sha256', json_encode($event, JSON_THROW_ON_ERROR)), ...$event];
    }

    private function reference(mixed $value): string
    {
        if ((! is_string($value) && ! is_int($value)) || (string) $value === '' || strlen((string) $value) > 255) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }

        return (string) $value;
    }
}
