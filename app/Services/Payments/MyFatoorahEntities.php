<?php

namespace App\Services\Payments;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Models\MyFatoorahEntity;

final class MyFatoorahEntities
{
    public function record(array $operation, array $data, array $payload, ?int $customerId): void
    {
        $kind = $operation['kind'];
        $reference = match ($kind) {
            'invoice' => $data['InvoiceId'] ?? data_get($data, 'Invoice.Id'),
            'session' => $data['SessionId'] ?? $payload['sessionId'] ?? null,
            'subscription' => $data['RecurringId'] ?? null,
            'supplier' => $data['SupplierCode'] ?? $payload['SupplierCode'] ?? null,
            'refund' => data_get($data, 'Refund.Id'),
            'shipment', 'transfer' => $data['InvoiceId'] ?? null,
            default => null,
        };
        if ($kind !== null && $operation['mutates'] && (! is_scalar($reference) || (string) $reference === '' || strlen((string) $reference) > 128)) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }
        if ($kind !== null && $reference !== null && (string) $reference !== '') {
            $status = $data['SupplierStatus'] ?? data_get($data, 'Invoice.Status') ?? data_get($data, 'Refund.Status') ?? ($kind === 'subscription' ? 'Draft' : 'created');
            $this->save($kind, (string) $reference, (string) $status, $data, $customerId);
        }
        if ($operation['name'] === 'subscriptions.list') {
            foreach ($data['RecurringPayment'] ?? [] as $subscription) {
                $this->save('subscription', (string) $subscription['RecurringId'], (string) $subscription['RecurringStatus'], $subscription, null);
            }
        }
        if ($operation['name'] === 'suppliers.list') {
            foreach ($data['items'] ?? [] as $supplier) {
                $this->save('supplier', (string) $supplier['SupplierCode'], (string) ($supplier['SupplierStatus'] ?? 'unknown'), $supplier, null);
            }
        }
        if (in_array($operation['name'], ['subscriptions.cancel', 'subscriptions.retry'], true)) {
            MyFatoorahEntity::query()->where('kind', 'subscription')->where('reference', $payload['recurringId'])->update(['needs_refresh' => true]);
        }
        if (in_array($operation['name'], ['shipping.orders', 'shipping.update', 'shipping.pickup'], true)) {
            foreach ($data['ShippingOrder'] ?? $data['items'] ?? [] as $shipment) {
                if (isset($shipment['OrderNumber'], $shipment['OrderStatus'])) {
                    $this->save('shipment', (string) $shipment['OrderNumber'], (string) $shipment['OrderStatus'], $shipment, null);
                }
            }
        }
    }

    /** Only signed fields are stored; unsigned event timestamps cannot order state transitions. */
    public function webhook(array $event): void
    {
        $data = $event['data'];
        [$kind, $reference] = match ($event['code']) {
            1 => ['invoice', data_get($data, 'Invoice.Id')],
            2 => ['refund', data_get($data, 'Refund.Id')],
            3 => ['settlement', data_get($data, 'Deposit.Reference')],
            4, 7 => ['supplier', data_get($data, 'Supplier.Code')],
            5 => ['subscription', data_get($data, 'Recurring.Id')],
            6 => ['dispute', data_get($data, 'Dispute.DisputeTransactionId')],
            default => [null, null],
        };
        if ($kind === null || $reference === null || strlen((string) $reference) > 128) {
            return;
        }
        $entity = MyFatoorahEntity::query()->firstOrCreate(['kind' => $kind, 'reference' => (string) $reference], ['status' => 'reported', 'snapshot' => []]);
        if (! $entity->exists) {
            $entity->forceFill(['status' => 'reported', 'snapshot' => []]);
        }
        $entity->forceFill(['needs_refresh' => true, 'last_event' => ['code' => $event['code'], 'signed_fields' => $data]])->save();
    }

    private function save(string $kind, string $reference, string $status, array $data, ?int $customerId): void
    {
        $entity = MyFatoorahEntity::query()->firstOrCreate(['kind' => $kind, 'reference' => $reference], ['status' => 'created', 'snapshot' => []]);
        $entity->forceFill(['status' => $status, 'snapshot' => $data, 'synced_at' => now(), 'needs_refresh' => false]);
        if ($customerId !== null && $entity->customer_id === null) {
            $entity->customer_id = $customerId;
        }
        $entity->save();
    }
}
