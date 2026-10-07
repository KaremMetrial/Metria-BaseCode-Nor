<?php

namespace App\Actions\Payments;

use App\Contracts\Audit\AuditLoggerInterface;
use App\DTOs\Audit\AuditEntry;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Models\MyFatoorahOperation;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\MyFatoorahCatalog;
use App\Services\Payments\MyFatoorahClient;
use App\Services\Payments\MyFatoorahEntities;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class RunMyFatoorahOperation
{
    public function __construct(private readonly MyFatoorahCatalog $catalog, private readonly MyFatoorahClient $client, private readonly MyFatoorahEntities $entities, private readonly AuditLoggerInterface $audit) {}

    /** @return array{operation_id:?string,status:string,result:array<string|int,mixed>} */
    public function execute(User $actor, string $name, array $input): array
    {
        if (! $actor->isAdmin() || ! $actor->isActive() || ! $actor->can('myfatoorah.manage')) {
            throw new DomainException(ErrorCode::FORBIDDEN);
        }
        $operation = $this->catalog->get($name);
        $this->requireFeatures($operation, $input);
        $customerId = isset($input['customer_id']) ? (int) $input['customer_id'] : null;
        $key = $input['idempotency_key'] ?? null;
        $payload = Arr::except($input, ['customer_id', 'idempotency_key', 'consent_reference']);
        if (! $operation['mutates']) {
            $result = $this->send($operation, $payload, null);
            $this->entities->record($operation, $result, $payload, null);

            return ['operation_id' => null, 'status' => 'succeeded', 'result' => $result];
        }
        $hash = hash('sha256', json_encode([$actor->id, $name, Arr::sortRecursive($input)], JSON_THROW_ON_ERROR));
        $record = DB::transaction(function () use ($actor, $customerId, $operation, $key, $hash): MyFatoorahOperation {
            MyFatoorahOperation::query()->insertOrIgnore([
                'uuid' => (string) Str::uuid(), 'actor_id' => $actor->id, 'customer_id' => $customerId,
                'operation' => $operation['name'], 'status' => 'ready', 'idempotency_key' => $key,
                'request_hash' => $hash, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $record = MyFatoorahOperation::query()->where('idempotency_key', $key)->lockForUpdate()->firstOrFail();
            if (! hash_equals($record->request_hash, $hash)) {
                throw new DomainException(ErrorCode::IDEMPOTENCY_CONFLICT);
            }

            return $record;
        }, 5);
        if ($record->status === 'succeeded' && $record->result !== null) {
            return ['operation_id' => $record->uuid, 'status' => 'succeeded', 'result' => $record->result];
        }
        if (! MyFatoorahOperation::query()->whereKey($record->id)->where('status', 'ready')->update(['status' => 'sending'])) {
            throw new DomainException(ErrorCode::RECONCILIATION_REQUIRED);
        }
        $submitted = false;
        try {
            $this->protectWalletPayments($operation, $payload);
            $submitted = true;
            $result = $this->send($operation, $payload, 'operation:'.$record->uuid);
            DB::transaction(function () use ($operation, $result, $payload, $customerId, $record, $actor): void {
                $this->entities->record($operation, $result, $payload, $customerId);
                $record->forceFill(['status' => 'succeeded', 'result' => $result])->save();
                $this->audit->record(new AuditEntry(AuditAction::MYFATOORAH_OPERATION, $record, actor: $actor, context: ['operation' => $operation['name']]));
            }, 5);
        } catch (Throwable $exception) {
            $record->forceFill(['status' => $submitted ? 'uncertain' : 'rejected'])->save();
            throw $exception;
        }

        return ['operation_id' => $record->uuid, 'status' => 'succeeded', 'result' => $result];
    }

    private function send(array $operation, array $payload, ?string $key): array
    {
        $payload = $this->normalize($operation['schema'], $payload);
        $path = $operation['path'];
        foreach ($operation['parameters'] as $parameter) {
            $path = str_replace('{'.$parameter.'}', rawurlencode((string) $payload[$parameter]), $path);
        }
        $query = Arr::only($payload, $operation['query']);
        $body = Arr::except($payload, [...$operation['parameters'], ...$operation['query']]);

        return $this->client->request($operation['method'], $path, $body, $query, $key);
    }

    private function normalize(array $schema, array $payload): array
    {
        foreach ($payload as $field => $value) {
            $property = $schema['properties'][$field] ?? [];
            $payload[$field] = match ($property['type'] ?? '') {
                'number' => (float) $value, 'integer' => (int) $value, 'boolean' => (bool) $value,
                'object' => $this->normalize($property, $value),
                'array' => ($property['items']['type'] ?? null) === 'object' ? array_map(fn (array $item): array => $this->normalize($property['items'], $item), $value) : $value,
                default => $value,
            };
        }

        return $payload;
    }

    private function requireFeatures(array $operation, array $payload): void
    {
        $features = [$operation['feature']];
        if (isset($payload['Suppliers']) || isset($payload['SupplierRefundedAmount'])) {
            $features[] = 'multivendor';
        }
        if (isset($payload['RecurringModel']) || ! empty($payload['IsRecurring'])) {
            $features[] = 'recurring';
        }
        if (! empty($payload['SaveToken']) || ($payload['TokenType'] ?? null) === 'mftoken') {
            $features[] = 'tokenization';
        }
        if (isset($payload['ShippingMethod'])) {
            $features[] = 'shipping';
        }
        if (data_get($payload, 'SourceOfFund.Token') || data_get($payload, 'SaveCardOptions.SaveToken')) {
            $features[] = 'tokenization';
        }
        if (in_array(data_get($payload, 'ThreeDS.Enabled'), [false, 0, '0'], true) || in_array(data_get($payload, 'ProcessingDetails.Bypass3DS'), [true, 1, '1'], true)) {
            $features[] = 'mit';
        }
        if (($payload['OperationType'] ?? null) === 'AUTHORIZE' || in_array(data_get($payload, 'ProcessingDetails.AutoCapture'), [false, 0, '0'], true)) {
            $features[] = 'auth_capture';
        }
        if (($payload['OperationType'] ?? null) === 'VERIFY') {
            $features[] = 'card_verification';
        }
        if (array_diff($features, config('myfatoorah.features', [])) !== []) {
            throw new DomainException(ErrorCode::PROVIDER_UNAVAILABLE);
        }
    }

    /** Wallet refunds must reserve the local balance through RefundPayment. */
    private function protectWalletPayments(array $operation, array $payload): void
    {
        $externalId = data_get($payload, 'Order.ExternalIdentifier') ?? ($payload['CustomerReference'] ?? null);
        if (is_string($externalId) && Payment::query()->where('uuid', $externalId)->exists()) {
            throw new DomainException(ErrorCode::INVALID_PAYMENT_STATUS);
        }
        if (! in_array($operation['name'], ['refunds.create', 'payments.update'], true)) {
            return;
        }
        $paymentId = $payload['PaymentId'] ?? $payload['paymentId'];
        $snapshot = $this->client->request('GET', '/v3/payments/'.rawurlencode((string) $paymentId));
        $reference = data_get($snapshot, 'Invoice.Id');
        if ($reference === null || Payment::query()->where('provider', 'myfatoorah')->where('provider_reference', (string) $reference)->exists()
            || Payment::query()->where('provider', 'myfatoorah')->where('uuid', (string) data_get($snapshot, 'Invoice.ExternalIdentifier'))->exists()) {
            throw new DomainException(ErrorCode::INVALID_PAYMENT_STATUS);
        }
    }
}
