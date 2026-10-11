# بوابات الدفع ووحدات الأموال

[الفهرس](../README.md) · [Client](../Client/payments.md) · [Vendor](../Vendor/payments.md) · [Webhooks](payment-webhooks.md)

## Payments, refunds and wallet adjustments

Wallet/payment endpoints use integer minor units: `1500` means `15.00` for `EGP`, `USD`, `SAR`, `AED`, `GBP`, `CAD`, and `QAR`, or `1.500` for `KWD`, `BHD`, `OMR`, and `JOD`. Do not send floating-point amounts to wallet endpoints. The configured maximum defaults to `99999999`. MyFatoorah merchant-service endpoints use decimal major units as documented below.

```sh
curl --fail-with-body "$API_BASE/client/payments" \
  -H "Authorization: Bearer $CLIENT_TOKEN" \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: checkout-example-001' \
  -d '{"amount":1500,"currency":"EGP","provider":"stripe"}'
```

A gateway must be configured; providers default to `disabled`. The `provider` body field can be omitted only when the operator has configured a valid default. The body never supplies `idempotency_key`; use the header. Keys are 1–128 characters from letters, digits, `.`, `_`, `:`, `-`.

Keep the same key **and payload** when retrying after a timeout or 503. Reusing a key with different inputs returns 409. Never start a new financial operation just to bypass an uncertain result. Old ambiguous attempts require server-side reconciliation; the operator runs `payments:reconcile` as described in the README.

The response may expose a provider `client_secret` only to the payment owner while the payment is pending/processing. Use the provider SDK for client confirmation. A signed webhook settles the payment and wallet; a client callback does not prove payment success.

Refunds use `POST /admin/payments/{payment}/refunds` with `amount` and the idempotency header. Partial refunds cannot exceed the remaining refundable balance. Pending refunds reserve the amount. Confirmed provider failure releases that reservation; an uncertain result remains pending for reconciliation.

Wallet adjustments use `POST /admin/wallets/{wallet}/adjustments` with `amount`, `direction` (`credit`/`debit`) and `reason`. The actor needs `wallets.adjust` and the permission matching the direction. Locked wallets and insufficient funds reject adjustments. The returned transaction includes the resulting balance.

## Stripe webhook

Configure Stripe to POST to `/api/v1/webhooks/payments/stripe`. Preserve the exact raw JSON body and supply `Stripe-Signature`; no bearer token is used. Signature timestamp tolerance is five minutes, and event mode must match the configured credentials. Bodies larger than 256 KiB are rejected.

Handled events: `payment_intent.succeeded`, `payment_intent.canceled`, `refund.created`, `refund.updated`, `refund.failed`. Duplicate deliveries are idempotent. Unrelated event types are acknowledged without settlement. Successful acknowledgement is HTTP 200 with `data: null`.

Never construct a signature using a secret in frontend code. Use Stripe's sandbox tooling or the automated webhook tests to exercise signature, replay and settlement behavior.


## إعداد المزود

## Multiple payment and SMS providers

Providers default to `disabled`. Configure `SMS_PROVIDER=twilio`, `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, and `TWILIO_FROM` for SMS. Configure `PAYMENT_PROVIDER=stripe`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, and the correct `STRIPE_LIVEMODE` for payments. Use test credentials and `STRIPE_LIVEMODE=false` for Stripe sandbox validation.

MyFatoorah supports wallet checkout/refunds and a permission-protected merchant module for subscriptions, marketplace suppliers/splits, transfers, shipping, embedded/token payments, capture/release, and reporting. See [MyFatoorah](../Admin/MyFatoorah/README.md). Set the merchant country, base currency, private API/webhook keys, live mode and approved feature flags from `.env.example`; the default example is Kuwait sandbox, not a live account. Admin routes require `myfatoorah.manage`. Raw-card processing is not enabled.

Registries in `config/payments.php` and `config/sms.php` accept multiple named configurations and drivers implementing their respective interfaces. A payment request may select a configured provider; a payment retains that provider for callbacks/refunds. SMS uses the configured default. Automatic cross-provider financial failover is not implemented because it can duplicate charges. Stripe and MyFatoorah have concrete adapters. Other gateway brands require a concrete adapter and contract tests; configuring an arbitrary brand name does not implement its API.

Wallet amounts are integer minor units. Payment creation and wallet/refund mutations require the `Idempotency-Key` HTTP header; replay the same key with the same payload after a timeout. Stripe callbacks use `POST /api/v1/webhooks/payments/{provider}` and authenticate the raw request body with the provider signature. Only provider settlement credits a wallet. Refunds reserve the wallet debit before the external call. Ambiguous retries stop after 23 hours for Stripe and 240 minutes for MyFatoorah. `php artisan payments:reconcile --payment=ID` reads authoritative provider state and safely applies recovered settlement; it never creates a charge or refund. The scheduler runs a bounded reconciliation batch every five minutes. Missing/ambiguous results remain pending and return a nonzero command exit code. Do not issue a fresh key to bypass this guard.


المبالغ التجارية في MyFatoorah تختلف عن شحن المحفظة:10.5 تعني 10.5 وحدة رئيسية في خدمات التاجر. لا تحول القيمة نفسها بين الواجهتين دون مراعاة عملة المسار.
