# Metrial API

Laravel 13 / PHP 8.5 monolith with MySQL 8, Redis queues and authenticated Socket.IO. The API has separate `/api/v1/admin`, `/api/v1/client`, and `/api/v1/vendor` surfaces. See `AUDIT_REPORT.txt` and `AUDIT_EVIDENCE.json` for verification and release conditions.

## Local setup

Install Composer and npm dependencies, copy `.env.example` to `.env`, and generate `APP_KEY` with `php artisan key:generate`. Configure a random `SOCKET_TOKEN_SECRET` of at least 32 bytes before starting Socket.IO; Laravel and Socket.IO must share it. `openssl rand -hex 32` generates a suitable value.

```sh
composer install
npm ci
npm --prefix docker/socketio ci
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --seed
```

The example DB credentials and development admin are for local use. Set unique secrets, `APP_ENV=production`, `APP_DEBUG=false`, and TLS before a deployment. The development admin is only seeded in local/development/testing. Do not run `migrate:fresh` against an existing application database.

Services: `app`, `web`, `db`, `redis`, `queue`, `scheduler`, `socketio`. Host DB/Redis ports bind to loopback. The scheduler republishes pending notification outbox entries; both scheduler and queue worker must run. Redis uses `noeviction` to protect queued work: monitor memory and failed jobs. Docker configuration validation is recorded in the audit; a complete production deployment was not performed.

## Authentication and locale

Admin login: `POST /api/v1/admin/auth/login` with email/password. Client/vendor login: `POST /api/v1/{actor}/auth/otp/request` with `country_id` and `phone`, then `/auth/otp/verify` with those fields, `challenge_id`, and `code`. Numbers are normalized to E.164. Send issued tokens as `Authorization: Bearer ...`. New vendors remain pending approval and may read their profile or log out.

Locale priority is explicit `locale` query / `X-Locale`, authenticated user preference, `Accept-Language`, then application default. Supported locales are `en` and `ar`. Error codes and identifiers remain stable across languages.

## Multiple payment and SMS providers

Providers default to `disabled`. Configure `SMS_PROVIDER=twilio`, `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, and `TWILIO_FROM` for SMS. Configure `PAYMENT_PROVIDER=stripe`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, and the correct `STRIPE_LIVEMODE` for payments. Use test credentials and `STRIPE_LIVEMODE=false` for Stripe sandbox validation.

Registries in `config/payments.php` and `config/sms.php` accept multiple named configurations and drivers implementing their respective interfaces. A payment request may select a configured provider; a payment retains that provider for callbacks/refunds. SMS uses the configured default. Automatic cross-provider financial failover is not implemented because it can duplicate charges. Additional gateway brands require a concrete adapter and contract tests; configuring an arbitrary brand name does not implement its API.

Wallet amounts are integer minor units. Payment creation and wallet/refund mutations require the `Idempotency-Key` HTTP header; replay the same key with the same payload after a timeout. Stripe callbacks use `POST /api/v1/webhooks/payments/{provider}` and authenticate the raw request body with the provider signature. Only provider settlement credits a wallet. Refunds reserve the wallet debit before the external call. An ambiguous operation older than 23 hours stops automatic provider retries and requires reconciliation against the provider record. Do not issue a fresh key to bypass this guard.

## Realtime

Obtain `{data: {token, expires_at}}` from `POST /api/v1/{actor}/realtime/token` using the API bearer token. Connect with Socket.IO websocket transport and `auth: {token}`. The server automatically joins the authenticated user's `private-user.ID` room. Listen for `notification.created`; fetch missed notifications from the authenticated notifications endpoint. Reconnect with a new credential after expiry (120 seconds by default). Blocking/logout revokes HTTP tokens; an already issued realtime credential lasts until its short expiry.

The relay accepts only server-published notification events. Keep `REDIS_PREFIX`, Redis connection settings, and `SOCKET_TOKEN_SECRET` consistent across app, worker, and Socket.IO. Notification delivery is at least once: deduplicate by notification ID. Database notifications and realtime delivery are implemented; FCM and general-purpose email notification channels are not implemented.

## Verification

```sh
php artisan test --compact
vendor/bin/pint --dirty --format agent
composer validate --strict
composer audit
npm audit
npm --prefix docker/socketio audit
npm run build
docker compose config --quiet
node --test docker/socketio/auth.test.cjs
```

SQLite skips five MySQL multi-process concurrency tests and one opt-in Redis integration test. To exercise those tests, use a disposable MySQL database with the full `DB_*` environment, set `RUN_REDIS_INTEGRATION=1` and isolated `REDIS_*` settings, then run the same Laravel suite. Tests migrate/reset the supplied test database. Set `TEST_REDIS_PORT` to a disposable Redis port to run `node --test docker/socketio/auth.test.cjs docker/socketio/integration.test.cjs`.

No PHPStan/Larastan configuration or dependency is installed. PHP syntax checks, formatting, runtime tests, and dependency audits are recorded separately; none is presented as static type analysis.
