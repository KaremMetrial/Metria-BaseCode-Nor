# Metrial API v1 — integration guide

> التوثيق المقسّم حسب الميزة والحساب: [docs/README.md](docs/README.md) — يشمل التدفقات الكاملة وأمثلة الطلبات والردود.

For the complete client account and notification flow, with Arabic explanations, cURL requests and JSON responses for every client endpoint, see [CLIENT_API_FLOW.md](CLIENT_API_FLOW.md).

The interactive reference is available at **`/docs/api`** and the OpenAPI 3.1 specification at **`/docs/api.json`**. Scramble generates the reference from the registered routes, Form Requests and response resources. It covers the registered operations under `/api/v1`, including public, client, vendor, administrator and payment webhook endpoints.

Use the interactive reference for the complete endpoint inventory, field constraints, response schemas and required permissions. This guide explains the workflows and shared contracts.

## Opening and exporting the reference

Start the application with its configured database and run the normal migrations before generating documentation. Scramble reads table metadata to infer resource field types; it does not execute endpoint actions, send SMS or create payments.

For the Docker development setup:

```sh
docker compose exec app php artisan migrate --no-interaction
docker compose exec app php artisan scramble:export --fail-on-unknown --no-interaction
```

Financial migrations install ledger immutability triggers. On a MySQL server with binary logging, run those migrations through a database administrator connection if the normal application user lacks trigger-creation privileges. Do not leave administrator credentials configured as the application’s runtime connection.

The export is written to `storage/app/private/openapi.json`. Import it into an OpenAPI 3.1-compatible client such as Postman, or generate a client with your preferred OpenAPI tooling. To choose another destination:

```sh
php artisan scramble:export --path=storage/app/private/openapi.json --fail-on-unknown --no-interaction
```

The UI and JSON endpoint allow anonymous access only when `APP_ENV` is `local` or `development`. In staging and production they require an **active administrator with the `super-admin` role**, authenticated with a Sanctum bearer token. `APP_DEBUG=true` does not grant access. An administrator can download the deployed specification with:

```sh
curl --fail-with-body "$APP_BASE/docs/api.json" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -o openapi.json
```

`APP_BASE` is your application origin without a trailing slash. `API_BASE` in the examples below is that origin followed by `/api/v1`. Tokens and other example variables must be populated with values from your own development or sandbox environment.

The UI loads Stoplight Elements from its CDN and needs internet access. The JSON specification and CLI export work without the UI. Interactive requests execute real endpoints: use sandbox accounts and provider credentials for financial testing.

## Endpoint groups

| Surface | Purpose | Access |
| --- | --- | --- |
| `/locations/*` | Country → governorate → city selection | Public; active records and ancestors only |
| `/categories` and `/categories/{category}` | Search and read categories | Public; active ancestor chain required |
| `/{actor}/auth/*` | Login, OTP verification and logout | Login/OTP public; logout requires token |
| `/{actor}/profile*` | Profile and verified phone changes | Matching actor token; writes require active account |
| `/{actor}/devices`, `/{actor}/notifications*`, `/{actor}/realtime/token` | Push registration and notifications | Active matching actor |
| `/client/wallet*`, `/vendor/wallet*` | Owned wallets and ledger history | Active matching actor |
| `/client/payments*`, `/vendor/payments*` | Create and inspect owned payments | Active matching actor |
| `/admin/locations/*`, `/admin/categories*` | Administrative reference-data CRUD | Active admin plus documented permissions |
| `/admin/users*` | User status and role administration | Active admin plus action-specific authority |
| `/admin/payments*`, `/admin/wallets*` | Financial oversight, refunds and adjustments | Active admin plus documented permissions |
| `/webhooks/payments/{provider}` | Stripe / MyFatoorah signed events | Provider signature, no bearer token |

`{actor}` means `client`, `vendor` or `admin`. Administrative login uses email/password; only client/vendor routes provide sign-in OTP. URL prefixes are enforced by actor middleware, not merely naming conventions. IDs for records owned by someone else return 404 on owner-scoped endpoints.

## Authentication workflow

1. Fetch `GET /locations/countries` and select a country ID.
2. Request a code with the selected country and mobile number:

```sh
curl --fail-with-body "$API_BASE/client/auth/otp/request" \
  -H 'Content-Type: application/json' \
  -H 'X-Locale: en' \
  -d '{"country_id":1,"phone":"+201012345678"}'
```

The country ID and phone above are illustrative. Use an enabled country and a phone number you control. The result contains `data.challenge_id`; the six-digit code is delivered by the configured SMS provider and never returned in the API response.

3. Verify the same phone and country using the returned challenge:

```sh
curl --fail-with-body "$API_BASE/client/auth/otp/verify" \
  -H 'Content-Type: application/json' \
  -d '{"country_id":1,"phone":"+201012345678","challenge_id":"11111111-1111-4111-8111-111111111111","code":"123456"}'
```

Replace the example challenge and code. The result contains `data.user` and `data.token`. Store the token securely and send `Authorization: Bearer <token>` on protected requests. Vendor onboarding uses the same flow under `/vendor`; new vendors are pending until approved. Pending vendors can get their profile and log out.

Administrators use `POST /admin/auth/login` with `{"email":"admin@example.test","password":"..."}`. No default production administrator is created by the seeders.

Defaults: OTP expires after 300 seconds, with five attempts, a 60-second resend cooldown and six issues/hour per phone across actors and purposes. These values are operator-configurable in `config/otp.php`. REST tokens expire after 1,440 minutes by default. `POST /{actor}/auth/logout` revokes the current token.

Changing a phone number uses `/profile/phone/request` followed by `/profile/phone/verify`. Successful verification revokes **all** of the account’s tokens. Authenticate again afterward.

## Requests, localization and responses

Send JSON with `Content-Type: application/json`. API errors return JSON even when `Accept` is omitted. Unsupported explicit locales return 422 `UNSUPPORTED_LOCALE`.

| Header or query | Meaning |
| --- | --- |
| `Authorization: Bearer …` | Sanctum token for protected operations |
| `X-Locale: en` or `ar` | Requested language |
| `?locale=en` or `ar` | Language override with the highest priority |
| `Accept-Language` | Negotiated language when no explicit/user preference applies |
| `X-Request-ID` | Optional 8–64 characters from letters, digits, `.`, `_`, `-`; invalid/missing values are replaced |
| `Idempotency-Key` | Required for payment creation, refunds and wallet adjustments |

Language precedence: query override → `X-Locale` → authenticated user preference → `Accept-Language` → English. Regional browser languages such as `ar-EG` resolve to `ar`; language preferences with `q=0` are ignored. An unsupported explicit locale still returns 422; its message uses the next supported preference (header, saved user language, browser language, then English).

Responses include `Content-Language: en` or `ar` and an echoed or generated `X-Request-ID` for tracing. Human-readable errors, validation messages, field labels, and translated resource fields use the resolved language, including routing errors and unexpected server failures. Field keys, IDs, enum values, and error codes remain stable. Provider snapshots and customer-entered text are preserved as received.

For Arabic validation, send `X-Locale: ar`, for example on an empty admin login request:

```json
{
  "success": false,
  "code": "VALIDATION_FAILED",
  "message": "البيانات المُرسلة غير صحيحة.",
  "errors": {
    "email": ["حقل البريد الإلكتروني مطلوب."],
    "password": ["حقل كلمة المرور مطلوب."]
  }
}
```

Success:

```json
{"success":true,"message":null,"data":{"id":42}}
```

`data` may be an object, an array or `null`. Deletions and logout return HTTP 200 with `data: null`; they do not return an empty 204 response. Creation endpoints generally return 201 as shown in the reference.

Validation error:

```json
{
  "success": false,
  "code": "VALIDATION_FAILED",
  "message": "The given data was invalid.",
  "errors": {"amount": ["The amount field is required."]}
}
```

Human-readable messages are illustrative and localized. Use `code` for decisions. Errors without field details have `errors: {}`, not an array.

| HTTP | Meaning and examples |
| --- | --- |
| 400 | `INVALID_WEBHOOK`: invalid provider signature or payload |
| 401 | `UNAUTHENTICATED`, `INVALID_CREDENTIALS` |
| 403 | `FORBIDDEN`, `ACCOUNT_PENDING`, `ACCOUNT_DISABLED` |
| 404 | `NOT_FOUND`: absent, hidden or inaccessible owned resource |
| 405 | `METHOD_NOT_ALLOWED`: incorrect HTTP method |
| 409 | `RESOURCE_CONFLICT`, `RESOURCE_IN_USE`, `PHONE_ALREADY_EXISTS`, `IDEMPOTENCY_CONFLICT`, `RECONCILIATION_REQUIRED`, `PAYMENT_ALREADY_REFUNDED` |
| 413 | Webhook body exceeds 262144 bytes; current application code is `INTERNAL_ERROR` |
| 422 | Validation, locale, phone, OTP, hierarchy or financial business-rule failure |
| 429 | `RATE_LIMITED`, `OTP_COOLDOWN`, `OTP_ATTEMPTS_EXCEEDED` |
| 500 | `INTERNAL_ERROR`: unexpected failure; retain the request ID |
| 503 | `PROVIDER_UNAVAILABLE`: required service unavailable or uncertain financial result |

`app/Enums/ErrorCode.php` is the stable code registry. `app/Support/ApiResponse.php` owns status mappings. Each OpenAPI error response lists the codes for its status; the applicable code depends on the operation and failed rule.

## Lists and translated resources

Paginated endpoints return:

```json
{
  "success": true,
  "message": null,
  "data": {
    "items": [],
    "meta": {"current_page":1,"last_page":1,"per_page":25,"total":0}
  }
}
```

Use `?page=2`. Page size is fixed at 25 except public category search, which accepts `per_page` from 1 to 100, `keyword`, `parent_id` and `sort` (`sort_order` or `id`). Location lists and the current user's wallet list return plain arrays in `data`.

Admin translation payloads are objects keyed by locale:

```json
{"translations":{"en":{"name":"Example"},"ar":{"name":"مثال"}}}
```

Creation requires the English name. Arabic is optional. PATCH permits omitted fields and locales, but every supplied locale object requires a name. Only `en` and `ar` keys are accepted. Country translations optionally contain `nationality`; categories optionally contain `description`. Public resources expose the selected language; authorized administrative resources include translations.

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

## Notifications and rate limits

Register a Firebase device token with `POST /{actor}/devices`. Each account supports ten devices. Registering a token owned by another account returns 409; unregister before account switching. Notifications are owner-scoped, paginated and can be marked read repeatedly without changing the original read time.

Get a short-lived Socket.IO credential from `POST /{actor}/realtime/token`. `expires_at` is a Unix timestamp; the default lifetime is 120 seconds. Connect using `auth: {token}`, listen for `notification.created`, and deduplicate by notification ID. Reconnect with a fresh credential after expiry. Fetch missed notifications over REST. The socket token does not authenticate REST endpoints.

| Limiter | Default |
| --- | --- |
| General API | 60 requests/minute per authenticated user, otherwise per IP |
| Admin login, payment creation, device registration, realtime token | 5/minute per IP; protected routes also use the general limiter |
| OTP request | 3/minute per IP, plus phone-specific cooldown/hourly limits |
| OTP verification | 5/minute per IP, plus challenge attempt limits |
| Webhook | 300/minute per IP |

HTTP throttling includes `Retry-After`. OTP domain cooldown/attempt errors do not always include that header. Avoid automatic retries that request fresh OTP challenges or create new financial keys.

## Maintenance and verification

```sh
php artisan test --compact tests/Feature/ApiDocumentationTest.php
php artisan scramble:export --fail-on-unknown --no-interaction
php artisan scramble:cache --no-interaction
```

Run these inside the application container when hostnames such as `db` and `redis` resolve only within Docker. Do not point tests at the application's normal database; use the configured isolated test environment.

`php artisan scramble:clear --no-interaction` invalidates documentation caching. If you use `scramble:cache`, clear/rebuild it after route, request, resource or documentation changes. Export and cache generation require migrated tables. CI exports the specification with `--fail-on-unknown` and uploads it with the test results. Regression tests compare every API route to its OpenAPI operation, exercise authentication boundaries and validate the project-specific schemas and documentation access restrictions.

The non-API operational routes are `GET /` (service identity) and `GET /up` (framework liveness). They are intentionally described here rather than mixed with the versioned JSON API. Liveness is not a readiness check: use `php artisan app:readiness` to check deployment configuration and dependencies. Package-internal routes and documentation assets are not application API operations.

## MyFatoorah integration

The integration covers MyFatoorah's current payment and merchant-service families using v3 for payments, sessions, capture/release and refunds, and v2 where the provider still requires it. The API contracts were checked against the [official documentation index](https://docs.myfatoorah.com/llms.txt) on 7 October 2026. Legacy aliases with equivalent v3 functionality are not duplicated. SDKs and e-commerce plugins are separate frontend/platform products, not additional merchant services.

### Configure the merchant account

Set these values in the deployment environment, never in committed source:

```dotenv
PAYMENT_PROVIDER=myfatoorah
MYFATOORAH_API_KEY=your-private-merchant-key
MYFATOORAH_WEBHOOK_SECRET=your-private-webhook-key
MYFATOORAH_LIVE=false
MYFATOORAH_COUNTRY=KWT
MYFATOORAH_CURRENCY=KWD
MYFATOORAH_REDIRECT_URL=https://your-frontend.example/payment/result
MYFATOORAH_FEATURES=payments,embedded,tokenization,reporting
```

Country and currency must match the merchant account. The defaults describe a Kuwait sandbox account; they do **not** identify your actual merchant account. For an Egyptian live account, use `EGY`, `EGP`, and `MYFATOORAH_LIVE=true` with the Egyptian account's private key. Each enabled country has its own key. All sandbox regions use `apitest.myfatoorah.com`; the live host is selected from a fixed allowlist. [Account keys and regional hosts](https://docs.myfatoorah.com/docs/api-key).

| Merchant country | Country value | Base currency | Live API host |
| --- | --- | --- | --- |
| Kuwait | KWT | KWD | api.myfatoorah.com |
| Bahrain | BHR | BHD | api.myfatoorah.com |
| Jordan | JOR | JOD | api.myfatoorah.com |
| Oman | OMN | OMR | api.myfatoorah.com |
| Saudi Arabia | SAU | SAR | api-sa.myfatoorah.com |
| United Arab Emirates | ARE | AED | api-ae.myfatoorah.com |
| Qatar | QAT | QAR | api-qa.myfatoorah.com |
| Egypt | EGY | EGP | api-eg.myfatoorah.com |

Run the normal migrations and `php artisan db:seed --class=RbacSeeder --no-interaction`. Grant `myfatoorah.manage` only to administrators who operate the merchant account. Existing finance roles do not receive it automatically; the existing super-admin gate has access. All integration routes require an active admin bearer token and this permission.

In the merchant portal, configure **webhook v2**, enable its secure key, and point it at `/api/v1/webhooks/payments/myfatoorah` on the application's public HTTPS domain. Subscribe to the events you use. The redirect URL is a customer navigation destination; visiting it or receiving `paymentId` is never proof of payment. Do not put API credentials or the webhook secret in frontend code.

Optional feature switches are `recurring`, `multivendor`, `transfers`, `shipping`, `auth_capture`, `card_verification`, and `mit`. Add them to the comma-separated `MYFATOORAH_FEATURES` only after activation for your merchant account and API key. A local switch does not enable a feature at MyFatoorah. The capabilities endpoint reports local configuration, while `payment-methods/list` asks the provider which payment methods your account actually supports.

### Wallet checkout and refunds

Use the existing client/vendor payment endpoints with `provider: "myfatoorah"`. Amounts remain **integer minor units**. KWD, BHD, OMR and JOD have three decimal places: `12345` means `12.345 KWD`; `12345` EGP means `123.45 EGP`. MyFatoorah wallet checkout accepts only the configured merchant base currency to avoid crediting a wallet from a converted display amount.

```json
{
  "amount": 12345,
  "currency": "KWD",
  "provider": "myfatoorah"
}
```

Supply `Idempotency-Key`. The payment response includes `payment_url` for its owner while pending/processing. Redirect the customer there. The hosted page offers the payment methods enabled for the merchant, including eligible cards, local payment networks, wallets and instalment providers. The integration does not maintain a hard-coded commercial promise that every method is available in every country. [Hosted checkout](https://docs.myfatoorah.com/docs/v3-hosted-payment-page), [payment-method discovery](https://docs.myfatoorah.com/reference/get-payment-methods).

Payment creation never credits a wallet. A signed webhook triggers a separate API inquiry; only matching invoice identity, successful status, base currency and exact gross amount can settle it. Provider fees do not reduce the customer wallet credit. A failed payment attempt leaves the invoice pending because another attempt can succeed. Duplicate events credit once. KWD amounts are converted exactly without rounding away the third decimal digit.

Wallet refunds continue through `POST /admin/payments/{payment}/refunds`. They reserve the wallet debit first, support partial refunds, and settle only against matching provider refund details. Ambiguous responses retain the reservation. `payments:reconcile` recovers payment status by invoice or external identifier, and can recover a missing refund ID by matching the refund UUID in `GetRefundStatus` before fetching v3 details. A missing invoice/transaction is not evidence that creating a replacement charge is safe.

MyFatoorah documents a **250-minute** provider idempotency cache. This application stops ambiguous wallet create/refund retries after **240 minutes** to leave a margin. Reconciliation remains available after that limit. The Stripe limit remains 23 hours. Never change the key to bypass an uncertain outcome. [Provider idempotency](https://docs.myfatoorah.com/docs/idempotency).

### Merchant service endpoints

All paths below are relative to `/api/v1/admin/integrations/myfatoorah`. Scramble documents every route, its provider field names, required inputs, authentication and responses. GET operations use query parameters; POST operations accept a JSON object directly. Keep provider field capitalization, such as `SupplierCode` and `ShippingMethod`.

Unlike wallet endpoints, merchant-service amounts use **decimal major units**, matching MyFatoorah: `12.345` means 12.345 KWD. These business invoices, subscriptions and shipments do not automatically credit application wallets. The business refund/capture endpoints reject wallet payment references; use the wallet refund action for those payments.

| Service | Endpoints | Feature / behavior |
| --- | --- | --- |
| Hosted invoices, email/SMS links, splits, token charges | `POST invoices/create` | `payments`; nested splits require `multivendor`; token use requires `tokenization` and consent reference |
| Invoice/payment inquiry | `GET invoices/get`, `GET invoices/find`, `GET payments/get` | `payments` |
| Merchant-enabled methods | `GET payment-methods/list` | `payments` |
| Embedded sessions and card verification | `POST sessions/create`, `GET sessions/get` | `embedded`; `OperationType: VERIFY` additionally requires `card_verification` |
| Native Apple/Google Pay and legacy embedded sessions | `POST sessions/initiate`, `POST sessions/update`, `POST payments/execute` | v2 session initialization, encrypted wallet token, then execution |
| Saved cards/token cancellation | `GET customers/get`, `POST tokens/cancel` | `tokenization`; returned card tokens are sensitive |
| Apple Pay domain registration | `POST apple-pay/register` | `embedded`; host Apple's verification file on your frontend domain first |
| Authorization/capture/release | `POST payments/update` | `auth_capture`; `CAPTURE` requires `Amount`; `RELEASE` releases authorization |
| Business refunds, including supplier distribution | `POST refunds/create`, `GET refunds/get` | `payments`; `SupplierRefundedAmount` requires `multivendor` |
| Provider-managed subscriptions | `POST subscriptions/create`, `GET subscriptions/list`, `POST subscriptions/cancel`, `POST subscriptions/retry` | `recurring`; retry applies to failed/uncompleted subscriptions, not arbitrary paused plans |
| Supplier onboarding and changes | `POST suppliers/create`, `POST suppliers/update`, `POST suppliers/commissions`, `POST suppliers/upload` | `multivendor`; KYC/changes can await provider approval |
| Supplier operations and bank directory | `GET suppliers/list`, `GET suppliers/get`, `GET suppliers/deposits`, `GET suppliers/documents`, `GET suppliers/dashboard`, `GET banks/list` | `multivendor` |
| Supplier balance transfers | `POST transfers/create` | `transfers`; `push` moves merchant balance to supplier, `pull` reverses it; this is not a general bank-payout API |
| Shipping invoice and quotation | `POST shipping/create`, `POST shipping/quote`, `GET shipping/countries`, `GET shipping/cities` | `shipping`; DHL=1, Aramex=2 |
| Shipping fulfillment | `GET shipping/orders`, `POST shipping/update`, `POST shipping/pickup` | `shipping`; pickup is POST locally even though the upstream endpoint uses GET, because it changes state |
| Reporting | `GET currencies/list`, `POST deposits/invoices`, `POST webhooks/search` | `reporting`; searching missed webhooks does not automatically apply unverified events |
| Integration administration | `GET capabilities`, `GET operations`, `GET operations/{id}`, `GET entities`, `GET entities/{id}`, `PATCH operations/{id}/resolution` | All require `myfatoorah.manage` |

All operations that change provider state require `Idempotency-Key`. The key is globally unique within this merchant integration and bound to the administrator and exact validated request. Successful repeats return the stored response. Only one request can claim a mutation; concurrent or uncertain repeats return `409 RECONCILIATION_REQUIRED`. Read-only POST operations (shipping quote, deposit report and webhook search) do not require a key.

Provider result objects preserve their field names inside `data.result`. Array results are wrapped in `{"items": [...]}`. Boolean/null results become `{"value": ..., "message": ...}`. Successful mutation responses include `data.operation_id`, a UUID. Operation history uses numeric record IDs and also exposes this UUID. HTTP 200 means the API operation succeeded; the underlying invoice, subscription, refund, KYC or shipment may still be pending. Consult its provider status.

Mutation results and business snapshots are encrypted at rest. Request bodies, raw files and saved tokens from requests are not persisted. Responses can contain payment tokens, session encryption keys and supplier banking information; keep them in your trusted backend, and pass only the fields required by the relevant provider frontend SDK. The service API is for administrators, not a client-side proxy. Optional `customer_id` associates a newly created record with a local user; it does not grant that user access to admin records.

### Workflow examples

**A subscription.** Obtain customer agreement first, enable recurring, then submit:

```http
POST /api/v1/admin/integrations/myfatoorah/subscriptions/create
Authorization: Bearer <admin-token>
Idempotency-Key: subscription-customer-123-plan-1
Content-Type: application/json
```

```json
{
  "customer_id": 123,
  "consent_reference": "signed-contract-2026-123",
  "PaymentMethodId": 2,
  "InvoiceValue": 10,
  "DisplayCurrencyIso": "KWD",
  "RecurringModel": {"RecurringType": "Monthly", "Iteration": 12, "RetryCount": 2}
}
```

Use an enabled recurring payment method for your account; `2` is the provider's documented sandbox example. Send the returned payment URL to the customer through your own product flow. The local subscription retains `RecurringId`; the provider starts it after the first payment. `Iteration: 0` continues until canceled. Custom intervals require 1–180 days. Query `subscriptions/list` to refresh states and subsequent invoice details. Cancel with `{"recurringId":"RECUR..."}`. `subscriptions/retry` asks MyFatoorah to retry an uncompleted recurring payment. [Recurring lifecycle](https://docs.myfatoorah.com/docs/recurring-payment).

**Merchant-managed recurring deductions.** Create an embedded session with save-card options and a stable `Customer.Reference`, obtain the token through `customers/get`, then use `invoices/create` with `PaymentMethod: "CARD"`, `SourceOfFund.Token`, and `consent_reference` for each authorized deduction. Each deduction gets a new business idempotency key. This supplies the charge workflow; your product decides the schedule and customer entitlement. FastPay/Bypass3DS/BypassCvv require merchant activation; setting `ThreeDS.Enabled: false` additionally requires the local `mit` feature. This application does not introduce an automatic off-session charging schedule. [Merchant-managed recurring requirements](https://docs.myfatoorah.com/docs/v3-vendor-managed-recurring).

**Marketplace onboarding, splits and transfers.** Query `banks/list`; create the supplier with contact and bank information; upload required documents; query supplier details until approved. Base64 document uploads are limited to 5 MiB decoded and checked for supported file extension/content type. A supplier-update response does not mean KYC approval. Create a business invoice with `Suppliers: [{"SupplierCode":7,"InvoiceShare":8}]`. Use supplier refund distribution when refunding its successful payment. Read supplier dashboard/deposits for balances and settlements. Transfer balances with `{"SupplierCode":7,"TransferAmount":5,"TransferType":"push"}` only when enabled. [Marketplace services](https://docs.myfatoorah.com/docs/multiple-suppliers), [balance transfer](https://docs.myfatoorah.com/reference/transfer-balance).

**Shipping.** Discover countries/cities for DHL or Aramex, request a quote with item dimensions and weight, then create the shipping invoice with consignee and invoice items. Use Latin item names/descriptions as required by the carriers. After payment, move the shipment to Prepared (`OrderStatusChangedTo: 1`); call `shipping/pickup`; then track/update fulfillment. Setting status 2 directly through `shipping/update` is rejected; pickup has its own operation. Pickup applies to the provider's prepared orders for the selected carrier, not a supplied invoice list. Carrier labels are obtained from the provider receipt/portal. [Shipping workflow and prerequisites](https://docs.myfatoorah.com/docs/shipping).

### Webhooks, reconciliation and operations

The gateway supports all seven documented webhook v2 event codes: payment (1), refund (2), balance settlement (3), supplier KYC (4), recurring (5), dispute (6), and supplier-update decision (7). HMAC validation uses the exact ordered fields for each code and constant-time comparison. Payment/refund amounts and identities come from a fresh authenticated API inquiry because the signature does not cover every payload field. [Webhook v2](https://docs.myfatoorah.com/docs/webhook-v2), [signature specification](https://docs.myfatoorah.com/docs/webhook-signature).

For business records, only signed fields are retained as event hints. `needs_refresh: true` tells the operator to call the corresponding inquiry/list route. Unsigned timestamps cannot safely order updates, so webhooks do not overwrite an authoritative snapshot. Settlement and dispute records are recorded for review; they do not automatically debit or credit a wallet. Review disputes in the merchant portal—there is no dispute-evidence submission API in this integration.

A timeout does not establish whether MyFatoorah performed a mutation. The operation becomes `uncertain`; do not submit a replacement key. Check its provider inquiry/merchant portal, then record evidence with:

```json
{
  "outcome": "confirmed_succeeded",
  "provider_reference": "the-verified-provider-reference",
  "evidence": "Verified in the merchant portal; support case/reference ..."
}
```

Send that body to `PATCH operations/{numeric-id}/resolution`. Use `confirmed_failed` only after confirming that no successful remote operation exists. This is an audited administrator attestation, not an automatic verification or another provider request. It cannot alter wallets, reopen the original key, or resolve a currently running request less than five minutes old. Refresh related entities through their inquiry endpoints. Full readiness reports unresolved/stalled business operations.

### Availability and verification boundary

Hosted/embedded checkout, provider tokens and encrypted native wallet tokens are supported. Raw card number/CVV payloads are rejected. Merchant-side direct card processing and decrypted Samsung Pay card processing require a separately established PCI-certified environment and are not enabled in this application. The existing integration exposes neither raw PAN entry nor a Samsung token decryption service. [Direct-card requirements](https://docs.myfatoorah.com/docs/v3-direct-payment), [Samsung Pay direct flow](https://docs.myfatoorah.com/docs/v3-samsung-pay-direct-integration).

Provider-managed subscriptions, marketplace splits, transfers, shipping, MIT, authorization/capture and verification remain subject to account approval, payment-method eligibility, country and API-key permissions. Enabling all local flags cannot guarantee remote availability. The provider's hosted/embedded UI handles eligible payment methods such as cards, local networks, wallets and BNPL without this backend storing card details.

Automated tests exercise the real application routes and database with mocked provider HTTP responses; no private merchant credentials are bundled. Before accepting real funds, validate in your own activated sandbox account: checkout and 3DS, duplicate callbacks, partial/full refunds, each advanced feature you enabled, its webhook delivery, and status reconciliation. Then switch country-specific credentials and live mode together. The browser redirect alone must never be used to mark a payment paid.

## Content pages, contact form and support chat

These are three independent features. Pages and the contact form are public; support chat requires a client account. Administration uses the existing admin bearer token and permissions. Requests use the standard success/error envelope, `X-Locale`, and `X-Request-ID` described above. Examples below use synthetic IDs: replace them with returned values.

### Installation and permissions

Apply migrations, seed the permission registry and the initial content drafts, then rebuild browser assets:

```sh
php artisan migrate --no-interaction
php artisan db:seed --class=RbacSeeder --no-interaction
php artisan db:seed --class=PageSeeder --no-interaction
php artisan db:seed --class=ContactSettingSeeder --no-interaction
npm run build
php artisan queue:restart --no-interaction
```

The page seeder creates `terms`, `privacy-policy`, `about-us`, `refund-policy` and `contact-us` as **unpublished drafts**. It never invents legal text or replaces existing content. An administrator must supply approved English and Arabic content and publish it. Contact settings start empty. No dependency was added.

Permissions:

| Permission | Operations |
| --- | --- |
| `pages.read` | List/read page drafts and revision history |
| `pages.manage` | Create pages and edit drafts |
| `pages.publish` | Publish/archive pages |
| `contact.settings` | Read/update structured contact settings |
| `contact.read` | Read submitted contact messages |
| `contact.manage` | Change contact status, also requires contact.read |
| `support.read` | Support inbox, conversation messages and read markers |
| `support.reply` | Send staff messages, also requires support.read |
| `support.manage` | Assignment/status changes, also requires support.read |

The `support-agent` role receives the contact inbox and support chat permissions. Content publication and contact settings remain separately granted. An active super-admin retains the existing bypass. Assignment organizes work; all staff with support.read can view the support inbox.

### Page administration and publication

| Method | Path after `/api/v1` | Input |
| --- | --- | --- |
| GET | `/pages` | Public, published pages only |
| GET | `/pages/{slug}` | Public localized published snapshot |
| GET | `/admin/pages` | Optional status=draft/published/archived; page |
| POST | `/admin/pages` | slug, translations, optional navigation fields |
| GET | `/admin/pages/{page}` | Draft and publication metadata |
| PATCH | `/admin/pages/{page}` | draft_version and changed fields |
| POST | `/admin/pages/{page}/publish` | draft_version, requires_acceptance |
| POST | `/admin/pages/{page}/archive` | draft_version |
| GET | `/admin/pages/{page}/revisions` | page; immutable bilingual snapshots |

`slug` is permanent. `sort_order`, `show_in_footer`, and `show_in_settings` control navigation. Content uses Markdown; use sanitized `content_html` for HTML rendering, or render `content` with a safe Markdown renderer. Do not insert the raw Markdown/title as HTML.

Create an extra page:

```sh
curl --fail-with-body "$API_BASE/admin/pages" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"slug":"delivery-info","sort_order":10,"translations":{"en":{"title":"Delivery information","content":"## Delivery\nContact support for delivery questions."},"ar":{"title":"معلومات التوصيل","content":"## التوصيل\nتواصل مع الدعم للاستفسار عن التوصيل."}}}'
```

HTTP 201 example:

```json
{"success":true,"message":null,"data":{"id":6,"slug":"delivery-info","status":"draft","draft_version":1,"sort_order":10,"show_in_footer":true,"show_in_settings":true,"translations":{"en":{"title":"Delivery information","content":"## Delivery\nContact support for delivery questions."},"ar":{"title":"معلومات التوصيل","content":"## التوصيل\nتواصل مع الدعم للاستفسار عن التوصيل."}},"published_revision_id":null,"terms_version":null,"published_version":null,"updated_at":"2026-10-11T12:00:00+00:00"}}
```

For the five seeded pages, find their numeric IDs with GET `/admin/pages` and PATCH the existing draft instead of creating the slug again. A create requires the default language; publication requires nonempty title/content in **both** English and Arabic.

```sh
curl --fail-with-body -X POST "$API_BASE/admin/pages/6/publish" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"draft_version":1,"requires_acceptance":false}'
```

HTTP 200 returns the same admin page shape with `status: "published"`, incremented `draft_version`, and `published_revision_id` / `published_version`. Use the returned draft_version for the next edit, publication or archive. Stale versions return `409 RESOURCE_CONFLICT`; reload before changing anything. An uncertain publication retry with an old version cannot create another revision.

GET `/pages/delivery-info` now returns HTTP 200:

```json
{"success":true,"message":null,"data":{"slug":"delivery-info","title":"Delivery information","content":"## Delivery\nContact support for delivery questions.","content_html":"<h2>Delivery</h2>\n<p>Contact support for delivery questions.</p>\n","version":1,"revision_id":1,"terms_version":null,"navigation":{"sort_order":10,"show_in_footer":true,"show_in_settings":true},"published_at":"2026-10-11T12:01:00+00:00","url":"https://api.example.com/pages/delivery-info?locale=en"}}
```

Browser URLs `/pages/{slug}?locale=ar` and `?locale=en` render the same published content with RTL/LTR layout. A draft change never changes public content or navigation until publication. Draft and archived pages return 404 publicly. Published terms cannot be archived (`409 RESOURCE_IN_USE`). Revision history is retained; there is no delete or edit-revision API.

### Terms and registration integration

Before the first terms publication, the previous `CLIENT_TERMS_VERSION` / `CLIENT_TERMS_URL` configuration remains effective. After publication, the database-backed terms become authoritative. GET `/client/auth/requirements` returns:

```json
{"success":true,"message":null,"data":{"terms_version":"terms-1-1","terms_revision_id":3,"terms_url":"https://api.example.com/pages/terms?locale=en","recovery":{"method":"support","support_url":"https://api.example.com/pages/contact-us?locale=en"}}}
```

Read the published terms and submit the **revision_id of the content actually displayed**. Both the public page and requirements expose this identifier. For managed terms, `terms_revision_id` is required in POST `/client/auth/complete-profile`:

```json
{"name":"Ahmed Ali","locale":"ar","timezone":"Africa/Cairo","terms_accepted":true,"terms_version":"terms-1-1","terms_revision_id":3}
```

Success still returns `{user, next_step: "home"}` inside data. `terms_version` is a consent version, while `terms_revision_id` identifies the exact immutable text. A terms publication with requires_acceptance=true advances the consent version; clients must review it again. A publication with false preserves the consent version, and acceptance of an older revision within that same consent version remains valid. The first publication always creates a new consent version.

The server rechecks the consent version and revision inside the transaction. A stale required version or invalid revision returns 422; fetch and display current terms again. Repeating the same acceptance preserves the original record. Historical pre-feature acceptances are retained with a null revision ID, since their exact text was not recorded.

When `contact-us` is published, recovery.support_url points to that browser page. Otherwise CLIENT_SUPPORT_URL remains the fallback. Neither contact submission nor support chat grants automatic account recovery or changes phone ownership.

### Structured contact settings

GET `/contact-settings` is public. GET `/admin/contact-settings` returns `{version, settings}`. PATCH the admin endpoint with the version and changed fields at the top level:

```json
{"version":1,"phone":"+201012345678","whatsapp":"+201012345678","social_links":[{"label":"Website","url":"https://example.com"}],"translations":{"en":{"intro":"How can we help?","address":"Cairo","working_hours":"Sunday–Thursday, 09:00–17:00"},"ar":{"intro":"كيف نساعدك؟","address":"القاهرة","working_hours":"الأحد–الخميس، 09:00–17:00"}}}
```

Success returns HTTP 200 with the updated `{version, settings}`. Stale versions return 409. Phone and WhatsApp accept international `+` numbers or null; social URLs accept HTTP(S) only. `social_links` replaces the entire list (send `[]` to clear it). Translation fields merge by locale. Public output is localized:

```json
{"success":true,"message":null,"data":{"phone":"+201012345678","whatsapp":"+201012345678","social_links":[{"label":"Website","url":"https://example.com"}],"intro":"How can we help?","address":"Cairo","working_hours":"Sunday–Thursday, 09:00–17:00"}}
```

### Contact us: one-way submissions

POST `/contact-messages` is public, with an optional client bearer token. No email or password is required. A supplied bearer token must be valid and belong to an active client; the server sets user_id itself. The entered phone is a callback number, not proof of account ownership.

```sh
curl --fail-with-body "$API_BASE/contact-messages" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' -H 'X-Locale: en' \
  -d '{"request_id":"11111111-1111-4111-8111-111111111111","name":"Ahmed Ali","country_id":1,"phone":"01012345678","subject":"Cannot sign in","message":"Please contact me about recovering access to my account."}'
```

Use a country_id returned by `/locations/countries`. HTTP 201:

```json
{"success":true,"message":"Your message has been received. Keep the reference for follow-up.","data":{"reference":"22222222-2222-4222-8222-222222222222"}}
```

Reuse request_id with the **same submitted fields** after an uncertain result. Matching retries return the same reference; changed data returns 409 IDEMPOTENCY_CONFLICT. The response never reveals phone/message/account data. There is no public lookup by reference and no chat created automatically. Limits: name 255 characters, subject 200, message 5000; three requests/minute/IP by default, shared with the browser form.

The published `/pages/contact-us` page includes the contact information, country selector and a server-rendered form. Browser submission is protected by CSRF and displays a reference after success.

Admin processing:

| Method | Path after `/api/v1` | Input/response |
| --- | --- | --- |
| GET | `/admin/contact-messages` | status, search, page; paginated items/meta |
| GET | `/admin/contact-messages/{message}` | ID, reference, user_id, name, phone, subject, message, status, timestamps |
| PATCH | `/admin/contact-messages/{message}/status` | `{"status":"reviewed"}`; updated record |

Statuses are new, reviewed, closed. Search matches exact reference/phone or part of the subject. Staff follow up through the configured contact channel; there is no email requirement or reply endpoint for this form.

### Support chat: client flow

All client paths below require an active client bearer token. Staff use the matching `/admin/support/...` endpoints with permissions.

```sh
curl --fail-with-body "$API_BASE/client/support/conversations" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"request_id":"33333333-3333-4333-8333-333333333333","client_message_id":"44444444-4444-4444-8444-444444444444","subject":"Help with my payment","body":"My payment is still pending. Can you check it?"}'
```

HTTP 201:

```json
{"success":true,"message":null,"data":{"id":12,"subject":"Help with my payment","status":"new","version":2,"last_message_id":81,"client_read_through":0,"staff_read_through":0,"unread_count":0,"created_at":"2026-10-11T12:10:00+00:00","updated_at":"2026-10-11T12:10:00+00:00"}}
```

A conversation creation is atomic with its first message. Reuse request_id and client_message_id for the same logical creation; changed payload returns 409. A client may create up to five unresolved conversations. Subject is at most 200 characters; message body at most 5000. Messages are plain text: render them as text, never HTML.

| Method | Path after `/api/v1/client` | Input |
| --- | --- | --- |
| GET | `/support/conversations` | Optional status, page; newest updated first |
| POST | `/support/conversations` | request_id, client_message_id, subject, body |
| GET | `/support/conversations/{conversation}` | Current metadata and read markers |
| GET | `/support/conversations/{conversation}/messages` | after_id (default 0), per_page (default 50, max 100) |
| POST | `/support/conversations/{conversation}/messages` | client_message_id, body |
| PATCH | `/support/conversations/{conversation}/read` | through_message_id |
| POST | `/support/conversations/{conversation}/close` | version from latest conversation response |

Send a subsequent message:

```json
{"client_message_id":"55555555-5555-4555-8555-555555555555","body":"The payment reference is 73."}
```

HTTP 201 example:

```json
{"success":true,"message":null,"data":{"id":82,"conversation_id":12,"sender_type":"client","is_mine":true,"client_message_id":"55555555-5555-4555-8555-555555555555","body":"The payment reference is 73.","created_at":"2026-10-11T12:11:00+00:00"}}
```

client_message_id is a UUID generated once per logical message, unique per sender. Retry the same UUID and text after a timeout. Reuse with another text or conversation returns 409. Deduplicate displayed messages by server ID and reconcile optimistic local messages by client_message_id. Another client's conversation returns 404 for every client operation.

GET `/support/conversations/12/messages?after_id=81&per_page=50`:

```json
{"success":true,"message":null,"data":{"items":[{"id":82,"conversation_id":12,"sender_type":"client","is_mine":true,"client_message_id":"55555555-5555-4555-8555-555555555555","body":"The payment reference is 73.","created_at":"2026-10-11T12:11:00+00:00"}],"meta":{"has_more":false,"next_after_id":82}}}
```

Messages are sorted by ID ascending. Persist next_after_id **after** storing all returned messages. While has_more=true, request again with after_id=next_after_id. Resume from that ID after reconnect; do not use the largest ID seen in a Socket signal as a substitute for downloaded messages.

Reading a message is explicit:

```json
{"through_message_id":82}
```

PATCH the `/read` endpoint only through the last message actually displayed. HTTP 200 returns updated conversation metadata. A marker cannot move backwards or refer to a different conversation. Client unread_count counts staff messages after client_read_through; staff unread_count counts client messages after staff_read_through. The staff marker is shared by the support team. Reading chat does not automatically mark its notification-center entries read; use the notification APIs for those.

### Support chat: staff flow and realtime

| Method | Path after `/api/v1/admin` | Input |
| --- | --- | --- |
| GET | `/support/conversations` | Optional status, page |
| GET | `/support/conversations/{conversation}` | Metadata, including user_id and assigned_to |
| GET / POST | `/support/conversations/{conversation}/messages` | Same pagination / message body contract as client |
| PATCH | `/support/conversations/{conversation}/read` | through_message_id |
| PATCH | `/support/conversations/{conversation}/assignment` | `{"version":2,"assigned_to":7}` or null |
| PATCH | `/support/conversations/{conversation}/status` | `{"version":3,"status":"resolved"}` |

Assignment requires an active admin with support.read and support.reply. Status/assignment use optimistic versions: reload on 409 instead of overwriting another staff action. Statuses: new, in_progress, resolved, closed. Staff replies move the conversation to in_progress; a reply to resolved reopens it. Closed conversations reject new messages, but an identical retry of a previously accepted message still succeeds. Staff can reopen a closed conversation. Client close requires the current version. Mutation throttling is 30/minute/account, in addition to the general API limiter.

The existing Socket.IO transport carries `notification.created` with an action such as:

```json
{"screen":"support_conversation","conversation_id":12,"last_message_id":82}
```

The action is inside the notification payload. There is **no separate** support Socket credential or public conversation room. Obtain `/client/realtime/token` or `/admin/realtime/token` and use the existing private-user connection. On a support action, fetch that conversation's metadata and missing messages from REST. Reconnect also refreshes the conversation list and message history. Refresh conversation metadata when the chat regains focus to update read markers; typing indicators and live read-receipt events are not implemented.

Each message and its generic recipient notifications are saved in the same transaction. Realtime publishing is queued after commit; the scheduled outbox sweep retries pending records after a crash. Staff replies notify the client; client messages notify the assigned eligible staff member or the authorized support inbox when unassigned. Push follows transactional_push preferences. Notification text is generic and does not expose message bodies. Delivery may repeat; use REST IDs as the source of truth. Queue workers, the scheduler and Socket.IO must run.

This release provides the backend APIs, public browser content pages and contact form. A client/admin chat interface connects to these APIs; attachments use the media workflow below; typing indicators are not implemented.

## Media: avatars, page images and support attachments

Uploads use private storage. Public page images are served through an application
endpoint only while listed in the current published revision. Support downloads
require a current bearer token and conversation access on every request.

### Upload and inspect

`POST /api/v1/{client|admin}/media` accepts **multipart/form-data**:

| Field | Meaning |
|---|---|
| `purpose` | `support`, or `page` for an admin with `pages.manage` |
| `file` | One uploaded file |

Support accepts JPEG, PNG, WebP (maximum 4096×4096) or PDF, up to 10 MiB.
Pages accept JPEG, PNG or WebP, up to 5 MiB and 4096×4096. SVG, GIF,
HTML, executable files and archives are rejected. MIME checks inspect the contents;
filename extensions must also be allowed. Images are decoded and re-encoded as PNG,
removing original metadata and trailing data. Returned size/type describe the stored
file, which may differ from the upload. The original name is display-only.

PDF files require ClamAV. If the scanner is unconfigured, unavailable, times out or
returns an operational error, the upload fails with `503 PROVIDER_UNAVAILABLE`.
Detected malware returns `422 VALIDATION_FAILED`. When a scanner is configured,
images pass through it too. Image re-encoding alone is not an antivirus scan.

```bash
# API_BASE is your API root, ending in /api/v1.
curl "$API_BASE/client/media" \
  -H "Authorization: Bearer $CLIENT_TOKEN" \
  -H 'Accept: application/json' \
  -F 'purpose=support' \
  -F 'file=@./screenshot.jpg'
```

Example response (`201`; actual URLs are absolute):

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": "019a0123-4567-7890-abcd-012345678901",
    "purpose": "support",
    "status": "ready",
    "name": "screenshot.jpg",
    "mime_type": "image/png",
    "size": 28412,
    "width": 1080,
    "height": 1920,
    "expires_at": "2026-10-12T12:00:00+00:00",
    "url": null,
    "download_url": "/api/v1/client/media/019a0123-4567-7890-abcd-012345678901/download"
  }
}
```

`GET /{client|admin}/media/{id}` returns the same metadata when authorized.
`GET /{client|admin}/media/{id}/download` returns **file bytes**, not JSON.
Send the bearer token; for a browser preview fetch an authenticated Blob and use
an object URL. Do not put tokens in query strings. PDF downloads use attachment
disposition. Missing, expired and inaccessible media return 404.

`DELETE /{client|admin}/media/{id}` discards your own unattached upload. It becomes
unreadable immediately; physical deletion is retried by the scheduler. Attached
files return `409 RESOURCE_IN_USE`. A still-processing upload returns
`409 RESOURCE_CONFLICT`. After physical deletion, subsequent requests return 404.

Uploads are limited to 10/minute per account, 20 pending/unattached uploads and
200 MiB stored per uploader by default. Discarded files count toward storage until
physical deletion succeeds. Unattached uploads expire after 24 hours. A new upload
request creates a new ID; reuse the returned ID when retrying a message. An upload
whose response was lost is eventually cleaned up if never attached.

### Send a support message with attachments

Upload the files first, then send their IDs in the existing message endpoint:

```http
POST /api/v1/client/support/conversations/42/messages
Authorization: Bearer <client-token>
Content-Type: application/json

{
  "client_message_id": "66c52883-39e7-48d8-b90b-d6123298bd71",
  "body": "This screenshot shows the problem.",
  "media_ids": ["019a0123-4567-7890-abcd-012345678901"]
}
```

The `201` response contains the existing message fields plus `attachments`, an
array of media metadata objects. Message history includes this array too.
`body` can be omitted when at least one attachment is supplied. Maximum five
unique IDs per message. The same fields work for an initial conversation message
(`POST /client/support/conversations`, with `subject` and `request_id`) and an
admin reply. Admin uploads require both `support.read` and `support.reply`.

Only ready, unexpired support uploads owned by the sender can be attached. An ID
can belong to one message only. Attachment linking, message creation and notification
outbox writes share a transaction. Retry with the same `client_message_id`, body
and set of media IDs; changed content returns `409 IDEMPOTENCY_CONFLICT`.
The recipient downloads through their own actor prefix. Staff need `support.read`;
clients must own the conversation. Notifications contain no attachment bytes or
private URLs. Closed conversations keep their readable history.

### Add images to a content page

1. An admin uploads with `purpose=page` and keeps the returned ID and `url`.
2. Include the ID in the page create/PATCH `media_ids` list (maximum 20).
3. Embed the returned public URL in translated Markdown, e.g. `![Description](URL)`.
4. Publish through the existing publish endpoint using the latest `draft_version`.

Before publication the public URL returns 404. Authorized admins can preview
through their authenticated download endpoint. `media_ids` on a PATCH replaces the
whole draft list; omitting it preserves the list. Draft changes do not alter live
images until publication. Archiving the page or publishing a revision without an
image revokes its public URL. Revision history retains its original media IDs.
Never-published images removed from a draft are scheduled for cleanup; images
referenced by historical revisions are retained. Existing assets of the same page
can be reused by other authorized editors; unbound uploads from other users and
assets belonging to another page cannot be claimed.

### Existing avatar endpoints

`POST /{client|admin|vendor}/profile/avatar` still accepts multipart `avatar`
(JPEG/PNG/WebP, 2 MiB, 2048×2048). `DELETE` still clears it. New avatar files use the
same media storage and inspection pipeline. The profile's `avatar_url` is a signed
five-minute URL. Possession grants access during that period, but replacing/deleting
the avatar or disabling/closing the account revokes access through this endpoint.
Old legacy URLs may remain usable until their original five-minute expiry.

### Runtime setup and maintenance

- Run `php artisan migrate --no-interaction`, then
  `php artisan media:import-avatars --no-interaction`. Import is repeatable and
  registers existing local files without moving or re-encoding them. Legacy avatars
  remain readable during transition; new uploads use `MEDIA_DISK`.
- The runtime needs GD with JPEG, PNG and WebP support for image processing.
- Set `MEDIA_CLAMAV_BINARY` to the absolute path of `clamscan`, with a readable,
  current virus-signature database. Keep signatures updated through the deployment's
  ClamAV maintenance. Do not enable PDF uploads by bypassing the scan.
- `MEDIA_DISK=local` by default. Use private storage only. Local multi-node deployments
  need a shared volume. An S3 disk also needs its Laravel filesystem adapter installed
  and a private bucket policy; changing the default disk does not move existing files.
- Run the normal Laravel scheduler. `media:prune` runs every ten minutes, processes
  up to 500 records and retries deletion failures. Operators can run
  `php artisan media:prune --limit=1000 --no-interaction` manually.
- Historical page assets and sent chat attachments are retained; this command is
  not a conversation/history retention policy.
