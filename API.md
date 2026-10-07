# Metrial API v1 — integration guide

The interactive reference is available at **`/docs/api`** and the OpenAPI 3.1 specification at **`/docs/api.json`**. Scramble generates the reference from the registered routes, Form Requests and response resources. It covers all **127 operations across 111 paths** under `/api/v1`, including public, client, vendor, administrator and payment webhook endpoints.

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
