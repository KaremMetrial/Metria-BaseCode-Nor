<?php

namespace App\Services\Payments;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;

final class MyFatoorahCatalog
{
    /** operation => [upstream contract, feature, changes remote state, local entity kind] */
    private const OPERATIONS = [
        'invoices.create' => ['create-payment', 'payments', true, 'invoice'],
        'invoices.get' => ['get-invoice-by-invoiceid', 'payments', false, 'invoice'],
        'invoices.find' => ['get-invoice-by-externalidentifier', 'payments', false, 'invoice'],
        'payments.execute' => ['execute-payment', 'payments', true, 'invoice'],
        'sessions.initiate' => ['initiate-session', 'embedded', true, 'session'],
        'sessions.update' => ['update-session', 'embedded', true, null],
        'payments.get' => ['get-payment-details', 'payments', false, 'invoice'],
        'payments.update' => ['update-payment', 'auth_capture', true, null],
        'payment-methods.list' => ['get-payment-methods', 'payments', false, null],
        'sessions.create' => ['create-session', 'embedded', true, 'session'],
        'sessions.get' => ['get-session-details', 'embedded', false, 'session'],
        'customers.get' => ['get-customer-details', 'tokenization', false, null],
        'tokens.cancel' => ['cancel-token', 'tokenization', true, null],
        'apple-pay.register' => ['register-apple-pay-domain', 'embedded', true, null],
        'refunds.create' => ['create-refund', 'payments', true, 'refund'],
        'refunds.get' => ['get-refund-details', 'payments', false, 'refund'],
        'subscriptions.create' => ['execute-payment', 'recurring', true, 'subscription'],
        'subscriptions.list' => ['get-recurring-payment', 'recurring', false, null],
        'subscriptions.cancel' => ['cancel-recurring-payment', 'recurring', true, null],
        'subscriptions.retry' => ['resume-recurring-payment', 'recurring', true, null],
        'suppliers.create' => ['create-supplier', 'multivendor', true, 'supplier'],
        'suppliers.update' => ['edit-supplier', 'multivendor', true, 'supplier'],
        'suppliers.commissions' => ['customize-supplier-commissions', 'multivendor', true, null],
        'suppliers.upload' => ['upload-supplier-document', 'multivendor', true, null],
        'suppliers.list' => ['get-suppliers', 'multivendor', false, null],
        'suppliers.get' => ['get-supplier-details', 'multivendor', false, 'supplier'],
        'suppliers.deposits' => ['get-supplier-deposits', 'multivendor', false, null],
        'suppliers.documents' => ['get-supplier-documents', 'multivendor', false, null],
        'suppliers.dashboard' => ['get-supplier-dashboard', 'multivendor', false, null],
        'transfers.create' => ['transfer-balance', 'transfers', true, 'transfer'],
        'shipping.create' => ['send-payment', 'shipping', true, 'shipment'],
        'shipping.countries' => ['get-countries', 'shipping', false, null],
        'shipping.cities' => ['get-cities', 'shipping', false, null],
        'shipping.quote' => ['calculate-shipping-charge', 'shipping', false, null],
        'shipping.orders' => ['get-shipping-order-list', 'shipping', false, null],
        'shipping.update' => ['update-shipping-status', 'shipping', true, null],
        'shipping.pickup' => ['request-pickup', 'shipping', true, null],
        'banks.list' => ['get-banks', 'multivendor', false, null],
        'currencies.list' => ['get-currencies-exchange-list', 'reporting', false, null],
        'deposits.invoices' => ['get-deposited-invoices', 'reporting', false, null],
        'webhooks.search' => ['get-webhooks', 'reporting', false, null],
    ];

    public function all(): array
    {
        return array_map(fn (string $name) => $this->get($name), array_keys(self::OPERATIONS));
    }

    public function get(string $name): array
    {
        $definition = self::OPERATIONS[$name] ?? throw new DomainException(ErrorCode::NOT_FOUND);
        $contracts = json_decode(file_get_contents(resource_path('myfatoorah-requests.json')), true, flags: JSON_THROW_ON_ERROR);
        [$contract, $feature, $mutates, $kind] = $definition;
        $operation = $contracts[$contract];
        $operation['schema'] = $this->schema($name, $operation['schema']);

        return [...$operation, 'name' => $name, 'feature' => $feature, 'mutates' => $mutates, 'kind' => $kind,
            'http_method' => $mutates ? 'POST' : $operation['method'],
            'route' => str_replace('.', '/', $name),
            'enabled' => in_array($feature, config('myfatoorah.features', []), true),
            'source' => 'https://docs.myfatoorah.com/reference/'.$contract,
        ];
    }

    private function schema(string $name, array $schema): array
    {
        if ($name === 'subscriptions.create') {
            $schema['required'] = array_values(array_unique([...$schema['required'], 'RecurringModel', 'PaymentMethodId']));
            $schema['properties']['RecurringModel']['required'] = ['RecurringType', 'Iteration'];
            $schema['properties']['RecurringModel']['properties']['RetryCount']['minimum'] = 0;
            $schema['properties']['RecurringModel']['properties']['RetryCount']['maximum'] = 5;
            $schema['properties']['RecurringModel']['properties']['IntervalDays']['minimum'] = 1;
            $schema['properties']['RecurringModel']['properties']['IntervalDays']['maximum'] = 180;
        }
        if (in_array($name, ['shipping.create', 'payments.execute', 'subscriptions.create'], true)) {
            $schema['properties']['InvoiceItems']['items']['properties']['Description'] = ['type' => 'string', 'maxLength' => 500];
        }
        if ($name === 'shipping.quote') {
            $schema['properties']['Items']['items']['required'] = ['Weight', 'Width', 'Height', 'Depth', 'Quantity', 'UnitPrice'];
        }
        if ($name === 'shipping.create') {
            $schema['properties']['InvoiceItems']['items']['required'] = ['ItemName', 'Quantity', 'UnitPrice', 'Weight', 'Width', 'Height', 'Depth'];
            $schema['required'] = array_values(array_unique([...$schema['required'], 'ShippingMethod', 'ShippingConsignee', 'InvoiceItems']));
        }
        if ($name === 'shipping.update') {
            $schema['required'] = ['ShippingMethod', 'InvoiceNumbers', 'OrderStatusChangedTo'];
            $schema['properties']['OrderStatusChangedTo']['enum'] = [0, 1, 3, 4];
        }
        if ($name === 'suppliers.commissions') {
            $schema['required'][] = 'SupplierCommissions';
        }

        return $schema;
    }
}
