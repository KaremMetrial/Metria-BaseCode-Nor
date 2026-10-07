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

Wallet amounts are integer minor units. Payment creation and wallet/refund mutations require the `Idempotency-Key` HTTP header; replay the same key with the same payload after a timeout. Stripe callbacks use `POST /api/v1/webhooks/payments/{provider}` and authenticate the raw request body with the provider signature. Only provider settlement credits a wallet. Refunds reserve the wallet debit before the external call. An ambiguous operation older than 23 hours stops charge/refund retries. `php artisan payments:reconcile --payment=ID` reads authoritative provider state and safely applies recovered settlement; it never creates a charge or refund. The scheduler runs a bounded reconciliation batch every five minutes. Missing/ambiguous results remain pending and return a nonzero command exit code. Do not issue a fresh key to bypass this guard.

## Realtime

Obtain `{data: {token, expires_at}}` from `POST /api/v1/{actor}/realtime/token` using the API bearer token. Connect with Socket.IO websocket transport and `auth: {token}`. The server automatically joins the authenticated user's `private-user.ID` room. Listen for `notification.created`; fetch missed notifications from the authenticated notifications endpoint. Reconnect with a new credential after expiry (120 seconds by default). Blocking/logout revokes HTTP tokens; an already issued realtime credential lasts until its short expiry.

The relay accepts only server-published notification events. Keep `REDIS_PREFIX`, Redis connection settings, and `SOCKET_TOKEN_SECRET` consistent across app, worker, and Socket.IO. Notification delivery is at least once: deduplicate by notification ID. Database, realtime, email and FCM notification delivery are implemented. Email and FCM are disabled until configured and commissioned. Each delivery has durable status and bounded retries; exactly-once external delivery is not guaranteed after a crash.

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

Larastan/PHPStan runs at level 5 across application, database and route code with no ignored-error baseline: `composer analyse`. The GitHub Actions workflow runs the MySQL/Redis suite, static analysis, audits, build and Socket.IO integration tests; hosted execution still requires the repository to be connected to GitHub.

## Email and push delivery

Set `NOTIFICATIONS_EMAIL_ENABLED=true` with a real `MAIL_MAILER` and its credentials. Only verified email addresses receive domain mail; log/array transports are rejected for delivery. `NOTIFICATIONS_FCM_ENABLED=true` requires `FCM_PROJECT_ID` and `GOOGLE_APPLICATION_CREDENTIALS`, pointing to a securely mounted Google service-account JSON file outside the repository/public directory. Google Auth obtains short-lived OAuth tokens; the FCM HTTP v1 API sends messages.

An active authenticated actor registers a token at `POST /api/v1/{actor}/devices` with `{ "token": "..." }` and removes it at `DELETE /api/v1/{actor}/devices/{id}`. Tokens are encrypted at rest, omitted from responses and limited to ten devices per user. Ownership cannot transfer through another user's registration. Clients should unregister their device before logout/account switching and deduplicate `notification_id` values.

The scheduler dispatches pending channel deliveries independently of realtime publication. Unregistered FCM devices are removed after an authoritative provider response. Provider failures remain pending with a delay; eight failed attempts mark a delivery failed. Inspect `notification_deliveries` and failed jobs, correct the cause, and deliberately reset a failed delivery for retry through authorized operations. Replays can duplicate an email if the worker dies after SMTP accepts it but before the database records success.

## Release checks

Run `php artisan app:readiness` in the deployment environment. It checks production settings, secrets being configured (never prints them), database/Redis connectivity, queue timeout configuration, cached routes/configuration, failed jobs and stale delivery/payment/refund work. `--configuration-only` skips connection probes. A passing result does not prove real provider delivery, TLS configuration outside the app, backups or production capacity.

The current local environment intentionally fails production readiness. Supply production configuration, run `php artisan optimize`, supervise queue/scheduler processes, complete live sandbox delivery/refund drills and rehearse restoration before release. `AUDIT_REPORT.txt` records a local MySQL backup/restore drill and a small sequential search benchmark, with their limitations.

## CI/CD on cPanel

GitHub Actions runs PHP formatting, PHPStan, the full MySQL/Redis suite, dependency audits, Socket.IO and deployment regression tests, and an isolated production-container smoke test. Docker is used in CI; cPanel receives a `release.tar.gz` archive containing production Composer/npm dependencies and compiled assets. It contains no application `.env`, development dependencies, or source checkout metadata.

Main-branch builds produce downloadable release artifacts. In **Actions → CI and cPanel deployment → Run workflow**, select `staging` or `production` to deploy after all checks pass. The default `none` builds only. Set the repository variable `STAGING_AUTO_DEPLOY=true` to deploy successful main pushes to staging automatically. Production deployment is manual. Create GitHub environments named `staging` and `production`, restrict deployment to `main`, and configure required reviewers for production. All third-party workflow actions are pinned to commit SHAs.

For each environment, configure:

| Type | Name | Value |
| --- | --- | --- |
| Variable | `DEPLOY_HOST` | SSH host, without a URL scheme |
| Variable | `DEPLOY_PORT` | SSH port; defaults to `22` |
| Variable | `DEPLOY_USER` | cPanel account username |
| Variable | `DEPLOY_PATH` | Absolute private deployment directory, e.g. `/home/ACCOUNT/metrial` |
| Secret | `SSH_PRIVATE_KEY` | Dedicated deployment key authorized for this account |
| Secret | `SSH_KNOWN_HOSTS` | Host key entry verified through your hosting provider; include `[host]:port` for a nonstandard port |

The deployment account needs SSH, Bash, `flock`, tar, gzip, curl, MySQL 8 `mysqldump`, PHP 8.5 with the application's extensions, and Node.js 22. PHP CLI and the domain's PHP version must match. cPanel must provide Redis access and allow WebSocket upgrades for the Node.js application. The wallet ledger migrations create database triggers: with MySQL binary logging enabled, the host must permit their creation (typically `log_bin_trust_function_creators=1`, configured by the host). Do not remove ledger immutability triggers to bypass a hosting restriction. See [MySQL's binary logging requirements](https://dev.mysql.com/doc/refman/8.0/en/stored-programs-logging.html).

Prepare the account once, outside `public_html`:

```text
/home/ACCOUNT/metrial/
  shared/
    .env                   Laravel production configuration
    socket.env             Socket.IO configuration
    deploy.conf            PHP path, database name, HTTPS health endpoints
    mysql-backup.cnf       MySQL backup account credentials (mode 600)
    storage/               Persistent uploads, sessions and logs
    socket-tmp/            Passenger restart marker
  incoming/                Uploaded archives and deployment scripts
  releases/                Versioned application code
  backups/                 Database snapshots (mode 600)
  current -> releases/...  Active code; created by deployment
  previous -> releases/... Previous code after a successful activation
```

Copy `.env.example` to `shared/.env` and configure production values, a persistent `APP_KEY`, MySQL, Redis, and the selected real payment/SMS providers. Set `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `APP_ENV=production`, `APP_DEBUG=false`, and HTTPS `APP_URL`. For staging, use sandbox provider credentials and `STRIPE_LIVEMODE=false`; the workflow passes `--sandbox-payments` to readiness and rejects live payment mode. Production keeps the default live-payment requirement (`STRIPE_LIVEMODE=true`). Both targets keep production Laravel security settings. Keep the Laravel maintenance driver at `file` so releases share maintenance state through shared storage. Copy `docker/socketio/.env.example` to `shared/socket.env`; match Laravel's `SOCKET_TOKEN_SECRET`, `REDIS_PREFIX`, Redis database and credentials. Copy the two templates in `docker/deploy/` to `shared/deploy.conf` and `shared/mysql-backup.cnf`, then replace their placeholders on the server. Restrict the shared configuration files to the cPanel account (`chmod 600`). The backup account needs permission to dump this database, including triggers. This application has no stored routines; add a host-approved routine backup if you introduce them. Keep `DB_DATABASE` in `deploy.conf` identical to Laravel's database.

Set the PHP domain document root to `/home/ACCOUNT/metrial/current/public`. Keep all other release and shared files outside public document roots. If the hosting plan cannot point a domain at this path, ask the host to configure it; do not expose the application root through `public_html`. PHP-FPM must revalidate symlinked code after a release; use `opcache.validate_timestamps=1` and `opcache.revalidate_freq=0` on this cPanel setup, or arrange a host-managed FPM reload.

Register a production Node.js application with root `/home/ACCOUNT/metrial/current/docker/socketio`, startup file `app.js`, Node.js 22, and a dedicated HTTPS socket subdomain. Serve it through Passenger, with static document root `public` under that app root; create an empty `public` directory if the panel requires one. Never make the Socket.IO application root itself a static document root because it contains the `.env` symlink. The archive already includes production `node_modules`; hosts using CloudLinux Node.js Selector may require their own dependency installation in its managed environment. Rehearse that step on staging before enabling automatic deployment.

Socket.IO uses `dotenv` before reading configuration. It loads `.env` beside the application regardless of the process working directory; `DOTENV_CONFIG_PATH` can select a shared file explicitly. cPanel process environment values take precedence, and its `PORT` takes precedence over `SOCKET_IO_PORT`. The deployment updates Passenger's shared `tmp/restart.txt` marker, following [cPanel's restart procedure](https://docs.cpanel.net/knowledge-base/web-services/how-to-install-a-node.js-application/#restart-the-application). First deployment may need the release unpacked and `current` symlink configured before the panel accepts application registration; resume by running the same uploaded script in `rollback` mode after registration if migrations already succeeded.

Add these cPanel cron entries, substituting your actual account and PHP path. The locks are required: deployments wait for running jobs, and cron skips new work during migration. This bounded queue worker suits shared hosting; continuously supervised workers need equivalent stop/drain coordination before using this deployment script.

```cron
* * * * * /usr/bin/flock -n /home/ACCOUNT/metrial/queue.lock /bin/sh -c 'cd /home/ACCOUNT/metrial/current && /opt/cpanel/ea-php85/root/usr/bin/php artisan queue:work redis --stop-when-empty --max-time=50 --timeout=60 --tries=5' >> /home/ACCOUNT/metrial/shared/storage/logs/queue-cron.log 2>&1
* * * * * /usr/bin/flock -n /home/ACCOUNT/metrial/scheduler.lock /bin/sh -c 'cd /home/ACCOUNT/metrial/current && /opt/cpanel/ea-php85/root/usr/bin/php artisan schedule:run' >> /home/ACCOUNT/metrial/shared/storage/logs/scheduler-cron.log 2>&1
```

Deployment validates the archive checksum and runtime configuration, enters maintenance, takes a compressed MySQL snapshot, runs forward migrations, verifies readiness, switches `current`, restarts Passenger, and checks both HTTPS health endpoints. Expect a maintenance window. Failed backups or migrations leave the old release selected and maintenance enabled. A failed health check after activation re-enables maintenance and preserves `previous` for recovery. Inspect the failure before rerunning; failed releases are retained deliberately.

Use **Actions → cPanel rollback** with an existing release ID (`commitSHA-runID-attempt`) to reactivate compatible old code. It preserves shared secrets/storage and never reverses migrations. Database changes must follow an expand/contract rollout for code rollback to work. Database restoration is a separate deliberate recovery operation; rehearse it, retain off-host backups, and rotate old release archives/backups according to your retention policy. No cPanel account or GitHub environment secrets have been configured by this repository change.
