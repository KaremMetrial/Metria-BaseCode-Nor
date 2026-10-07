<?php

namespace Tests\Feature;

use App\Actions\Payments\CreatePayment;
use App\Models\MyFatoorahEntity;
use App\Models\MyFatoorahOperation;
use App\Models\User;
use App\Services\Payments\MyFatoorahCatalog;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MyFatoorahOperationsTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/admin/integrations/myfatoorah/';

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.providers.myfatoorah.secret' => 'test-api-key', 'payments.providers.myfatoorah.country' => 'KWT', 'payments.providers.myfatoorah.livemode' => false,
            'payments.providers.myfatoorah.webhook_secret' => 'webhook-secret',
            'myfatoorah.features' => ['payments', 'embedded', 'tokenization', 'reporting', 'recurring', 'multivendor', 'transfers', 'shipping', 'auth_capture', 'card_verification', 'mit']]);
    }

    private function admin(): User
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo('myfatoorah.manage');
        Sanctum::actingAs($admin);

        return $admin;
    }

    #[DataProvider('operations')]
    public function test_service_endpoint_sends_correct_provider_contract_and_records_mutations(string $name, string $method, string $upstreamMethod, string $path, array $payload, array $result, bool $mutates, ?string $query = null): void
    {
        $this->admin();
        Http::preventStrayRequests();
        Http::fake([
            'https://apitest.myfatoorah.com'.$path.'*' => Http::response(['IsSuccess' => true, 'Data' => $result]),
            'https://apitest.myfatoorah.com/v3/payments/payment-business' => Http::response(['IsSuccess' => true, 'Data' => ['Invoice' => ['Id' => 'business-invoice']]]),
        ]);
        $response = $this->json($method, self::BASE.str_replace('.', '/', $name), $payload, ['Idempotency-Key' => 'operation-key'])->assertOk()->assertJsonPath('data.status', 'succeeded');
        Http::assertSent(fn ($request) => $request->method() === $upstreamMethod && parse_url($request->url(), PHP_URL_PATH) === $path
            && ($query === null || str_contains($request->url(), $query)) && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && (! $mutates || $request->hasHeader('Idempotency-Key', 'operation:'.$response->json('data.operation_id'))));
        foreach ($payload as $field => $value) {
            if (in_array($field, ['paymentId', 'InvoiceId', 'externalIdentifier', 'sessionId', 'Reference', 'refundId', 'consent_reference'], true)) {
                continue;
            }
            Http::assertSent(fn ($request) => $request->method() === $upstreamMethod && parse_url($request->url(), PHP_URL_PATH) === $path && $request[$field] == $value);
        }
        if ($mutates) {
            $this->assertDatabaseHas('my_fatoorah_operations', ['operation' => $name, 'status' => 'succeeded']);
            $this->assertDatabaseHas('audit_logs', ['action' => 'myfatoorah.operation']);
            $count = count(Http::recorded());
            $this->json($method, self::BASE.str_replace('.', '/', $name), $payload, ['Idempotency-Key' => 'operation-key'])->assertOk()->assertJsonPath('data.operation_id', $response->json('data.operation_id'));
            Http::assertSentCount($count);
        } else {
            $this->assertDatabaseCount('my_fatoorah_operations', 0);
        }
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public static function operations(): array
    {
        $supplier = ['SupplierName' => 'Example supplier', 'Mobile' => '96512345678', 'Email' => 'supplier@example.test'];
        $recipient = ['PersonName' => 'Test customer', 'Mobile' => '96512345678', 'LineAddress' => 'Street 1', 'CityName' => 'Kuwait', 'CountryCode' => 'KW'];

        return [
            'hosted and split invoices' => ['invoices.create', 'POST', 'POST', '/v3/payments', ['Order' => ['Amount' => 10, 'Currency' => 'KWD'], 'Suppliers' => [['SupplierCode' => 7, 'InvoiceShare' => 8]]], ['InvoiceId' => 'invoice-business', 'PaymentURL' => 'https://demo.myfatoorah.com/payment'], true],
            'invoice inquiry' => ['invoices.get', 'GET', 'GET', '/v3/invoices/100', ['InvoiceId' => '100'], ['Invoice' => ['Id' => '100', 'Status' => 'PAID']], false],
            'external reference' => ['invoices.find', 'GET', 'GET', '/v3/invoices/externalIdentifier/order-7', ['externalIdentifier' => 'order-7'], ['Invoice' => ['Id' => '100']], false],
            'legacy wallet session' => ['sessions.initiate', 'POST', 'POST', '/v2/InitiateSession', ['CustomerIdentifier' => 'customer1'], ['SessionId' => 'session1'], true],
            'native wallet token' => ['sessions.update', 'POST', 'POST', '/v2/UpdateSession', ['SessionId' => 'session1', 'Token' => 'encrypted-wallet-token', 'TokenType' => 'applepay'], [], true],
            'legacy session payment' => ['payments.execute', 'POST', 'POST', '/v2/ExecutePayment', ['SessionId' => 'session1', 'InvoiceValue' => 10], ['InvoiceId' => 77], true],
            'payment inquiry' => ['payments.get', 'GET', 'GET', '/v3/payments/100', ['paymentId' => '100'], ['Invoice' => ['Id' => '100']], false],
            'capture' => ['payments.update', 'POST', 'PUT', '/v3/payments/payment-business', ['paymentId' => 'payment-business', 'OperationType' => 'CAPTURE', 'Amount' => 10], ['Invoice' => ['Id' => 'business-invoice']], true],
            'enabled methods' => ['payment-methods.list', 'GET', 'GET', '/v3/payment-methods', [], ['PaymentMethods' => [['ApiName' => 'KNET']]], false],
            'embedded session' => ['sessions.create', 'POST', 'POST', '/v3/sessions', ['PaymentMode' => 'COMPLETE_PAYMENT', 'Order' => ['Amount' => 10]], ['SessionId' => 'session1', 'EncryptionKey' => 'secret-encryption-key'], true],
            'session inquiry' => ['sessions.get', 'GET', 'GET', '/v3/sessions/session1', ['sessionId' => 'session1'], ['IsUsed' => true], false],
            'saved customer cards' => ['customers.get', 'GET', 'GET', '/v3/customers/customer1', ['Reference' => 'customer1'], ['Reference' => 'customer1', 'Cards' => []], false],
            'cancel card token' => ['tokens.cancel', 'POST', 'POST', '/v2/CancelToken', ['Token' => 'token1'], [], true, 'Token=token1'],
            'apple domain' => ['apple-pay.register', 'POST', 'POST', '/v2/RegisterApplePayDomain', ['DomainName' => 'checkout.example.test'], [], true],
            'business refund' => ['refunds.create', 'POST', 'POST', '/v3/refunds', ['PaymentId' => 'payment-business', 'Amount' => 1], ['Refund' => ['Id' => 'refund-business', 'Status' => 'Pending']], true],
            'refund status' => ['refunds.get', 'GET', 'GET', '/v3/refunds/refund1', ['refundId' => 'refund1'], ['Refund' => ['Id' => 'refund1', 'Status' => 'Refunded']], false],
            'subscription signup' => ['subscriptions.create', 'POST', 'POST', '/v2/ExecutePayment', ['InvoiceValue' => 10, 'PaymentMethodId' => 2, 'RecurringModel' => ['RecurringType' => 'Monthly', 'Iteration' => 12], 'consent_reference' => 'signed-contract-17'], ['InvoiceId' => 12, 'RecurringId' => 'RECUR1'], true],
            'subscription status' => ['subscriptions.list', 'GET', 'GET', '/v2/GetRecurringPayment', [], ['RecurringPayment' => [['RecurringId' => 'RECUR1', 'RecurringStatus' => 'Active']]], false],
            'subscription cancel' => ['subscriptions.cancel', 'POST', 'POST', '/v2/CancelRecurringPayment', ['recurringId' => 'RECUR1'], [], true, 'recurringId=RECUR1'],
            'subscription retry failed' => ['subscriptions.retry', 'POST', 'POST', '/v2/ResumeRecurringPayment', ['recurringId' => 'RECUR1'], [], true, 'recurringId=RECUR1'],
            'supplier signup' => ['suppliers.create', 'POST', 'POST', '/v2/CreateSupplier', $supplier, ['SupplierCode' => 7], true],
            'supplier changes' => ['suppliers.update', 'POST', 'POST', '/v2/EditSupplier', [...$supplier, 'SupplierCode' => 7], ['SupplierCode' => 7], true],
            'supplier commissions' => ['suppliers.commissions', 'POST', 'POST', '/v2/CustomizeSupplierCommissions', ['SupplierCode' => 7, 'SupplierCommissions' => [['PaymentMethodId' => 2, 'CommissionPercentage' => 2]]], [], true],
            'supplier KYC upload' => ['suppliers.upload', 'POST', 'PUT', '/v2/UploadSupplierDocument', ['SupplierCode' => 7, 'FileType' => 5, 'FileUpload' => ['FileName' => 'document.pdf', 'MediaType' => 'application/pdf', 'Buffer' => 'JVBERi0xLjQK']], [], true],
            'suppliers list' => ['suppliers.list', 'GET', 'GET', '/v2/GetSuppliers', [], [['SupplierCode' => 7, 'SupplierStatus' => 'Active']], false],
            'supplier details' => ['suppliers.get', 'GET', 'GET', '/v2/GetSupplierDetails', ['SupplierCode' => 7], ['SupplierCode' => 7, 'SupplierStatus' => 'Active'], false, 'SupplierCode=7'],
            'supplier deposits' => ['suppliers.deposits', 'GET', 'GET', '/v2/GetSupplierDeposits', ['SupplierCode' => 7], [], false, 'SupplierCode=7'],
            'supplier documents' => ['suppliers.documents', 'GET', 'GET', '/v2/GetSupplierDocuments', ['SupplierCode' => 7], [], false, 'SupplierCode=7'],
            'supplier dashboard' => ['suppliers.dashboard', 'GET', 'GET', '/v2/GetSupplierDashboard', ['SupplierCode' => 7], [], false, 'SupplierCode=7'],
            'supplier transfer' => ['transfers.create', 'POST', 'POST', '/v2/TransferBalance', ['SupplierCode' => 7, 'TransferAmount' => 5, 'TransferType' => 'push'], ['InvoiceId' => 88], true],
            'shipping invoice' => ['shipping.create', 'POST', 'POST', '/v2/SendPayment', ['CustomerName' => 'Test customer', 'InvoiceValue' => 10, 'NotificationOption' => 'LNK', 'ShippingMethod' => 1, 'ShippingConsignee' => $recipient, 'InvoiceItems' => [['ItemName' => 'Book', 'Quantity' => 1, 'UnitPrice' => 10, 'Weight' => 1, 'Width' => 10, 'Height' => 10, 'Depth' => 10]]], ['InvoiceId' => 89], true],
            'shipping countries' => ['shipping.countries', 'GET', 'GET', '/v2/GetCountries', [], [['CountryCode' => 'KW']], false],
            'shipping cities' => ['shipping.cities', 'GET', 'GET', '/v2/GetCities', ['shippingMethod' => 1, 'countryCode' => 'KW'], [], false, 'countryCode=KW'],
            'shipping quote' => ['shipping.quote', 'POST', 'POST', '/v2/CalculateShippingCharge', ['ShippingMethod' => 1, 'CountryCode' => 'KW', 'CityName' => 'Kuwait', 'Items' => [['Weight' => 1, 'Width' => 10, 'Height' => 10, 'Depth' => 10, 'Quantity' => 1, 'UnitPrice' => 10]]], ['Fees' => 1.5], false],
            'shipping orders' => ['shipping.orders', 'GET', 'GET', '/v2/GetShippingOrderList', ['ShippingMethod' => 2, 'orderStatus' => 1], ['ShippingOrder' => [['OrderNumber' => 89, 'OrderStatus' => 'Prepared']]], false, 'orderStatus=1'],
            'shipping prepare' => ['shipping.update', 'POST', 'POST', '/v2/UpdateShippingStatus', ['ShippingMethod' => 1, 'InvoiceNumbers' => [89], 'OrderStatusChangedTo' => 1], ['ShippingOrder' => [['OrderNumber' => 89, 'OrderStatus' => 'Prepared']]], true],
            'shipping pickup has side effects despite upstream GET' => ['shipping.pickup', 'POST', 'GET', '/v2/RequestPickup', ['ShippingMethod' => 1], [['OrderNumber' => 89, 'OrderStatus' => 'RequestPickup']], true, 'ShippingMethod=1'],
            'bank directory' => ['banks.list', 'GET', 'GET', '/v2/GetBanks', [], [['Value' => 1, 'Text' => 'Example bank']], false],
            'exchange rates' => ['currencies.list', 'GET', 'GET', '/v2/GetCurrenciesExchangeList', [], [['Text' => 'KWD', 'Value' => '1.0']], false],
            'settlement report' => ['deposits.invoices', 'POST', 'POST', '/v2/GetDepositedInvoices', ['DepositReference' => 'deposit1'], [], false],
            'missed webhooks' => ['webhooks.search', 'POST', 'POST', '/v2/GetWebhooks', ['Page' => 1, 'Status' => 'Failed'], [], false],
        ];
    }

    public function test_client_and_ordinary_admin_cannot_use_merchant_operations(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://apitest.myfatoorah.com/*' => Http::response([])]);
        $this->getJson(self::BASE.'capabilities')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson(self::BASE.'capabilities')->assertForbidden();
        $this->seed(RbacSeeder::class);
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson(self::BASE.'transfers/create', ['SupplierCode' => 1, 'TransferAmount' => 1, 'TransferType' => 'push'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_timeout_preserves_durable_uncertain_operation_and_never_resubmits_transfer(): void
    {
        $this->admin();
        Http::preventStrayRequests();
        Http::fake(['https://apitest.myfatoorah.com/v2/TransferBalance' => Http::failedConnection()]);
        $payload = ['SupplierCode' => 7, 'TransferAmount' => 5, 'TransferType' => 'push'];
        $this->postJson(self::BASE.'transfers/create', $payload, ['Idempotency-Key' => 'transfer1'])->assertServiceUnavailable();
        $this->postJson(self::BASE.'transfers/create', $payload, ['Idempotency-Key' => 'transfer1'])->assertConflict()->assertJsonPath('code', 'RECONCILIATION_REQUIRED');
        $this->assertDatabaseHas('my_fatoorah_operations', ['status' => 'uncertain']);
        $this->getJson(self::BASE.'operations')->assertOk()->assertJsonPath('data.items.0.status', 'uncertain')->assertJsonMissingPath('data.items.0.idempotency_key');
        Http::assertSentCount(1);
    }

    public function test_reused_key_with_different_payload_returns_conflict_and_secrets_are_encrypted(): void
    {
        $this->admin();
        Http::preventStrayRequests();
        Http::fake(['https://apitest.myfatoorah.com/v3/sessions' => Http::response(['IsSuccess' => true, 'Data' => ['SessionId' => 'session1', 'EncryptionKey' => 'private-session-key']])]);
        $payload = ['PaymentMode' => 'COMPLETE_PAYMENT', 'Order' => ['Amount' => 10]];
        $this->postJson(self::BASE.'sessions/create', $payload, ['Idempotency-Key' => 'session'])->assertOk();
        $payload['Order']['Amount'] = 20;
        $this->postJson(self::BASE.'sessions/create', $payload, ['Idempotency-Key' => 'session'])->assertConflict();
        $this->assertStringNotContainsString('private-session-key', MyFatoorahOperation::firstOrFail()->getRawOriginal('result'));
        $this->assertStringNotContainsString('private-session-key', MyFatoorahEntity::firstOrFail()->getRawOriginal('snapshot'));
        Http::assertSentCount(1);
    }

    public function test_invalid_sensitive_and_conditional_fields_are_rejected_before_http(): void
    {
        $this->admin();
        Http::preventStrayRequests();
        Http::fake(['https://apitest.myfatoorah.com/*' => Http::response([])]);
        $this->postJson(self::BASE.'invoices/create', ['Order' => ['Amount' => 10], 'SourceOfFund' => ['Card' => ['Number' => '4111111111111111']]], ['Idempotency-Key' => 'card'])->assertUnprocessable()->assertJsonValidationErrors('SourceOfFund');
        $this->postJson(self::BASE.'subscriptions/create', ['InvoiceValue' => 10, 'PaymentMethodId' => 2, 'RecurringModel' => ['RecurringType' => 'Custom', 'Iteration' => 12]], ['Idempotency-Key' => 'subscription'])->assertUnprocessable()->assertJsonValidationErrors(['consent_reference', 'RecurringModel.IntervalDays']);
        $this->postJson(self::BASE.'shipping/update', ['ShippingMethod' => 1, 'InvoiceNumbers' => [89], 'OrderStatusChangedTo' => 2], ['Idempotency-Key' => 'pickup'])->assertUnprocessable()->assertJsonValidationErrors('OrderStatusChangedTo');
        $this->postJson(self::BASE.'payments/update', ['paymentId' => 'business', 'OperationType' => 'CAPTURE'], ['Idempotency-Key' => 'capture'])->assertUnprocessable()->assertJsonValidationErrors('Amount');
        $this->postJson(self::BASE.'invoices/create', ['Order' => ['Amount' => 10], 'NotificationOption' => 'EMAIL'], ['Idempotency-Key' => 'email'])->assertUnprocessable()->assertJsonValidationErrors('Customer.Email');
        Http::assertNothingSent();
    }

    public function test_disabled_feature_fails_closed_and_capabilities_do_not_expose_secrets(): void
    {
        $this->admin();
        config(['myfatoorah.features' => ['payments']]);
        Http::preventStrayRequests();
        Http::fake(['https://apitest.myfatoorah.com/*' => Http::response([])]);
        $this->postJson(self::BASE.'transfers/create', ['SupplierCode' => 1, 'TransferAmount' => 1, 'TransferType' => 'push'], ['Idempotency-Key' => 'disabled'])->assertServiceUnavailable();
        $response = $this->getJson(self::BASE.'capabilities')->assertOk()->assertJsonCount(41, 'data.operations');
        $this->assertStringNotContainsString('test-api-key', $response->getContent());
        Http::assertNothingSent();
    }

    public function test_signed_supplier_and_subscription_events_mark_records_for_refresh_without_overwriting_state(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://apitest.myfatoorah.com/*' => Http::response([])]);
        $data = ['Supplier' => ['Code' => 7], 'KycDecision' => ['Status' => 'APPROVED'], 'UnsignedAccountNumber' => 'attacker-value'];
        $signature = base64_encode(hash_hmac('sha256', 'Supplier.Code=7,KycDecision.Status=APPROVED', 'webhook-secret', true));
        $this->postJson('/api/v1/webhooks/payments/myfatoorah', ['Event' => ['Code' => 4], 'Data' => $data], ['MyFatoorah-Signature' => $signature])->assertOk();
        $entity = MyFatoorahEntity::firstOrFail();
        $this->assertTrue($entity->needs_refresh);
        $this->assertSame('reported', $entity->status);
        $this->assertArrayNotHasKey('UnsignedAccountNumber', $entity->last_event['signed_fields']);
        $this->assertDatabaseCount('wallet_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_manual_resolution_requires_evidence_and_does_not_repeat_remote_transfer(): void
    {
        $this->admin();
        Http::preventStrayRequests();
        Http::fake(['https://apitest.myfatoorah.com/v2/TransferBalance' => Http::failedConnection()]);
        $payload = ['SupplierCode' => 7, 'TransferAmount' => 5, 'TransferType' => 'push'];
        $this->postJson(self::BASE.'transfers/create', $payload, ['Idempotency-Key' => 'resolve-transfer'])->assertServiceUnavailable();
        $record = MyFatoorahOperation::firstOrFail();
        $this->patchJson(self::BASE.'operations/'.$record->id.'/resolution', ['outcome' => 'confirmed_succeeded'])->assertUnprocessable()->assertJsonValidationErrors(['evidence', 'provider_reference']);
        $this->patchJson(self::BASE.'operations/'.$record->id.'/resolution', ['outcome' => 'confirmed_succeeded', 'provider_reference' => 'deposit-17', 'evidence' => 'Confirmed in merchant portal, support case 17.'])->assertOk()->assertJsonPath('data.status', 'confirmed_succeeded');
        $this->postJson(self::BASE.'transfers/create', $payload, ['Idempotency-Key' => 'resolve-transfer'])->assertConflict();
        $this->assertDatabaseHas('audit_logs', ['action' => 'myfatoorah.operation_resolved']);
        $this->assertDatabaseCount('wallet_transactions', 0);
        Http::assertSentCount(1);
    }

    public function test_merchant_initiated_gate_cannot_be_bypassed_with_numeric_boolean(): void
    {
        $this->admin();
        config(['myfatoorah.features' => ['payments', 'tokenization']]);
        Http::preventStrayRequests();
        Http::fake(['https://apitest.myfatoorah.com/v3/payments' => Http::response([])]);
        $this->postJson(self::BASE.'invoices/create', ['Order' => ['Amount' => 10], 'ThreeDS' => ['Enabled' => '0']], ['Idempotency-Key' => 'mit-disabled'])->assertServiceUnavailable();
        Http::assertNothingSent();
    }

    public function test_full_service_catalog_has_endpoint_contract_coverage(): void
    {
        $implemented = array_column(app(MyFatoorahCatalog::class)->all(), 'name');
        $tested = array_map(fn (array $case): string => $case[0], self::operations());
        $this->assertEqualsCanonicalizing($implemented, array_values($tested));
    }

    #[DataProvider('businessEvents')]
    public function test_each_business_webhook_verifies_its_own_signature_fields(int $code, array $data, string $canonical, string $kind, string $reference): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://apitest.myfatoorah.com/*' => Http::response([])]);
        $signature = base64_encode(hash_hmac('sha256', $canonical, 'webhook-secret', true));
        $this->postJson('/api/v1/webhooks/payments/myfatoorah', ['Event' => ['Code' => $code], 'Data' => $data], ['MyFatoorah-Signature' => $signature])->assertOk();
        $this->assertDatabaseHas('my_fatoorah_entities', ['kind' => $kind, 'reference' => $reference, 'needs_refresh' => true]);
        $this->assertDatabaseCount('wallet_transactions', 0);
        Http::assertNothingSent();
    }

    public static function businessEvents(): array
    {
        return [
            [3, ['Deposit' => ['Reference' => 'D1', 'ValueInBaseCurrency' => '10.500', 'NumberOfTransactions' => 2]], 'Deposit.Reference=D1,Deposit.ValueInBaseCurrency=10.500,Deposit.NumberOfTransactions=2', 'settlement', 'D1'],
            [5, ['Recurring' => ['Id' => 'RECUR1', 'Status' => 'UNCOMPLETED', 'InitialInvoiceId' => '100']], 'Recurring.Id=RECUR1,Recurring.Status=UNCOMPLETED,Recurring.InitialInvoiceId=100', 'subscription', 'RECUR1'],
            [6, ['Dispute' => ['DisputeTransactionId' => 'D2', 'Status' => 'PENDING'], 'Invoice' => ['Id' => '100', 'Status' => 'PAID', 'ExternalIdentifier' => null], 'Transaction' => ['Status' => 'SUCCESS', 'PaymentId' => 'P1']], 'Dispute.DisputeTransactionId=D2,Dispute.Status=PENDING,Invoice.Id=100,Invoice.Status=PAID,Transaction.Status=SUCCESS,Transaction.PaymentId=P1,Invoice.ExternalIdentifier=', 'dispute', 'D2'],
            [7, ['Supplier' => ['Code' => 7], 'RequestStatus' => ['Status' => 'APPROVED']], 'Supplier.Code=7,RequestStatus.Status=APPROVED', 'supplier', '7'],
        ];
    }

    public function test_record_reads_and_inflight_resolution_keep_data_under_admin_permission(): void
    {
        $admin = $this->admin();
        $record = MyFatoorahOperation::factory()->create(['actor_id' => $admin->id, 'status' => 'sending']);
        $entity = MyFatoorahEntity::factory()->create(['snapshot' => ['SupplierCode' => 7]]);
        $this->getJson(self::BASE.'operations/'.$record->id)->assertOk()->assertJsonPath('data.status', 'sending');
        $this->getJson(self::BASE.'entities')->assertOk()->assertJsonPath('data.items.0.snapshot.SupplierCode', 7);
        $this->getJson(self::BASE.'entities/'.$entity->id)->assertOk()->assertJsonPath('data.snapshot.SupplierCode', 7);
        $this->patchJson(self::BASE.'operations/'.$record->id.'/resolution', ['outcome' => 'confirmed_failed', 'evidence' => 'Operator checked the portal.'])->assertConflict();
        $this->assertSame('sending', $record->fresh()->status);
    }

    public function test_business_refund_cannot_bypass_wallet_debit_reservation(): void
    {
        $this->admin();
        Http::preventStrayRequests();
        Http::fake(['https://apitest.myfatoorah.com/v3/payments' => Http::response(['IsSuccess' => true, 'Data' => ['InvoiceId' => 'wallet-invoice', 'PaymentURL' => 'https://demo.myfatoorah.com/checkout']])]);
        $payment = app(CreatePayment::class)->execute(User::factory()->create(), 1000, 'KWD', 'myfatoorah', 'wallet-payment');
        Http::fake(['https://apitest.myfatoorah.com/v3/payments/wallet-provider-id' => Http::response(['IsSuccess' => true, 'Data' => ['Invoice' => ['Id' => 'wallet-invoice', 'ExternalIdentifier' => $payment->uuid]]])]);
        $this->postJson(self::BASE.'refunds/create', ['PaymentId' => 'wallet-provider-id', 'Amount' => 1], ['Idempotency-Key' => 'bypass'])->assertUnprocessable()->assertJsonPath('code', 'INVALID_PAYMENT_STATUS');
        $this->assertDatabaseCount('payment_refunds', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseHas('my_fatoorah_operations', ['status' => 'rejected']);
        Http::assertSentCount(1);
    }

    #[DataProvider('providerFailures')]
    public function test_invalid_provider_response_never_records_success(int $status, mixed $body): void
    {
        $this->admin();
        Http::preventStrayRequests();
        Http::fake(['https://apitest.myfatoorah.com/v3/sessions' => Http::response($body, $status)]);
        $this->postJson(self::BASE.'sessions/create', ['PaymentMode' => 'COMPLETE_PAYMENT', 'Order' => ['Amount' => 10]], ['Idempotency-Key' => 'provider-error'])->assertServiceUnavailable();
        $this->assertDatabaseHas('my_fatoorah_operations', ['status' => 'uncertain']);
        $this->assertDatabaseCount('my_fatoorah_entities', 0);
        Http::assertSentCount(1);
    }

    public static function providerFailures(): array
    {
        return [[401, ['IsSuccess' => false]], [429, ['IsSuccess' => false]], [500, ['IsSuccess' => false]], [200, 'gateway HTML error'], [200, ['IsSuccess' => false, 'ValidationErrors' => [['Name' => 'Order.Amount', 'Error' => 'Invalid']]]], [200, ['IsSuccess' => true, 'Data' => []]]];
    }
}
