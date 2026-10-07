<?php

namespace App\Services\Payments;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;

final class MyFatoorahSignature
{
    private const FIELDS = [
        1 => ['Invoice.Id', 'Invoice.Status', 'Transaction.Status', 'Transaction.PaymentId', 'Invoice.ExternalIdentifier'],
        2 => ['Refund.Id', 'Refund.Status', 'Amount.ValueInBaseCurrency', 'ReferencedInvoice.Id'],
        3 => ['Deposit.Reference', 'Deposit.ValueInBaseCurrency', 'Deposit.NumberOfTransactions'],
        4 => ['Supplier.Code', 'KycDecision.Status'],
        5 => ['Recurring.Id', 'Recurring.Status', 'Recurring.InitialInvoiceId'],
        6 => ['Dispute.DisputeTransactionId', 'Dispute.Status', 'Invoice.Id', 'Invoice.Status', 'Transaction.Status', 'Transaction.PaymentId', 'Invoice.ExternalIdentifier'],
        7 => ['Supplier.Code', 'RequestStatus.Status'],
    ];

    /** @return array{code:int,data:array<string,mixed>,fingerprint:string} */
    public function verify(string $body, array $headers, string $secret): array
    {
        $payload = json_decode($body, true, 64);
        $code = data_get($payload, 'Event.Code');
        $signature = $headers['myfatoorah-signature'][0] ?? null;
        if ($secret === '' || ! is_string($signature) || ! is_int($code) || ! isset(self::FIELDS[$code]) || ! is_array($payload['Data'] ?? null)) {
            throw new DomainException(ErrorCode::INVALID_WEBHOOK);
        }
        $parts = [];
        $signed = [];
        foreach (self::FIELDS[$code] as $field) {
            $value = data_get($payload['Data'], $field);
            if ($value !== null && ! is_string($value) && ! is_int($value) && ! is_float($value)) {
                throw new DomainException(ErrorCode::INVALID_WEBHOOK);
            }
            $parts[] = $field.'='.($value ?? '');
            data_set($signed, $field, $value);
        }
        $canonical = implode(',', $parts);
        $expected = base64_encode(hash_hmac('sha256', $canonical, $secret, true));
        if (! hash_equals($expected, $signature)) {
            throw new DomainException(ErrorCode::INVALID_WEBHOOK);
        }

        return ['code' => $code, 'data' => $signed, 'fingerprint' => hash('sha256', $code.':'.$canonical)];
    }
}
