# النشر وCI/CD

[الفهرس](../README.md) · [الإعدادات](configuration.md) · [الأوامر](commands.md)

هذا دليل تشغيل لتنفيذ النشر عند الحاجة، ولا يعني أن بيئة إنتاج نُشرت خلال كتابة الوثائق.

## مكان Socket.IO وإعداد cPanel

كود الخدمة مستقل داخل `socketio/` في جذر المشروع بجانب `routes/`:

```text
project/
  app/
  routes/
  public/              Laravel document root
  socketio/
    app.js             Passenger startup file
    socket.io.config.cjs
    .env.example
    package.json
    public/            Socket application static document root
    Dockerfile
  docker/deploy/       Packaging and deployment scripts
```

في إعداد Node.js Application على cPanel، استخدم:

| الإعداد | القيمة مع آلية releases الحالية |
|---|---|
| Application root | `/home/ACCOUNT/metrial/current/socketio` |
| Startup file | `app.js` |
| Environment | `production` |
| Node.js | `22` حسب متطلبات المشروع |
| Static document root | `/home/ACCOUNT/metrial/current/socketio/public` |
| Laravel document root | `/home/ACCOUNT/metrial/current/public` |

المسار تمثيلي؛ ACCOUNT حساب الاستضافة، وmetrial مسار النشر المختار. بعض واجهات cPanel تطلب Application root نسبيًا لمجلد الحساب: `metrial/current/socketio`. التطبيق يحتاج دعم Node.js/Passenger وWebSocket واتصال Redis. تغيير موقع المجلد وحده لا يفعّل هذه الخدمات لدى المستضيف. [إعداد Node.js الرسمي في cPanel](https://docs.cpanel.net/knowledge-base/web-services/how-to-install-a-node.js-application/).

عند الرفع اليدوي دون releases استخدم مسار `<project>/socketio`، وثبّت الاعتماديات بـ`npm --prefix socketio ci --omit=dev --ignore-scripts`. جهّز ملف البيئة من `socketio/.env.example` بقيم الخادم الفعلية، أواضبط DOTENV_CONFIG_PATH إلى ملف خاص. حافظ على تطابق SOCKET_TOKEN_SECRET وREDIS_PREFIX وإعدادات Redis مع Laravel، واضبط CORS_ORIGIN لدومين الواجهة. استخدم مدير التطبيقات لإعادة التشغيل. لا تضع كود الخدمة أوأسرارها داخل Laravel public.

لو كان التطبيق مسجلًا قبل النقل، غيّر Application root من `current/docker/socketio` إلى `current/socketio` وحدث static document root ثم أعد تشغيله أثناء تحديث النشر. سكربت rollback يدعم الإصدارات القديمة بإنشاء رابط توافق داخل الإصدار القديم، كي يظل Application root الجديد صالحًا. هذا التغيير يجهّز الملفات؛ لم يغيّر إعدادات حساب cPanel فعليًا.

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

Copy `.env.example` to `shared/.env` and configure production values, a persistent `APP_KEY`, MySQL, Redis, and the selected real payment/SMS providers. Set `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `APP_ENV=production`, `APP_DEBUG=false`, and HTTPS `APP_URL`. For staging, use sandbox provider credentials and `STRIPE_LIVEMODE=false`; the workflow passes `--sandbox-payments` to readiness and rejects live payment mode. Production keeps the default live-payment requirement (`STRIPE_LIVEMODE=true`). Both targets keep production Laravel security settings. Keep the Laravel maintenance driver at `file` so releases share maintenance state through shared storage. Copy `socketio/.env.example` to `shared/socket.env`; match Laravel's `SOCKET_TOKEN_SECRET`, `REDIS_PREFIX`, Redis database and credentials. Copy the two templates in `docker/deploy/` to `shared/deploy.conf` and `shared/mysql-backup.cnf`, then replace their placeholders on the server. Restrict the shared configuration files to the cPanel account (`chmod 600`). The backup account needs permission to dump this database, including triggers. This application has no stored routines; add a host-approved routine backup if you introduce them. Keep `DB_DATABASE` in `deploy.conf` identical to Laravel's database.

Set the PHP domain document root to `/home/ACCOUNT/metrial/current/public`. Keep all other release and shared files outside public document roots. If the hosting plan cannot point a domain at this path, ask the host to configure it; do not expose the application root through `public_html`. PHP-FPM must revalidate symlinked code after a release; use `opcache.validate_timestamps=1` and `opcache.revalidate_freq=0` on this cPanel setup, or arrange a host-managed FPM reload.

Register a production Node.js application with root `/home/ACCOUNT/metrial/current/socketio`, startup file `app.js`, Node.js 22, and a dedicated HTTPS socket subdomain. Serve it through Passenger, with static document root `public` under that app root; the repository and release artifact include this directory. Never make the Socket.IO application root itself a static document root because it contains the `.env` symlink. The archive already includes production `node_modules`; hosts using CloudLinux Node.js Selector may require their own dependency installation in its managed environment. Rehearse that step on staging before enabling automatic deployment.

Socket.IO uses `dotenv` before reading configuration. It loads `.env` beside the application regardless of the process working directory; `DOTENV_CONFIG_PATH` can select a shared file explicitly. cPanel process environment values take precedence, and its `PORT` takes precedence over `SOCKET_IO_PORT`. The deployment updates Passenger's shared `tmp/restart.txt` marker, following [cPanel's restart procedure](https://docs.cpanel.net/knowledge-base/web-services/how-to-install-a-node.js-application/#restart-the-application). First deployment may need the release unpacked and `current` symlink configured before the panel accepts application registration; resume by running the same uploaded script in `rollback` mode after registration if migrations already succeeded.

Add these cPanel cron entries, substituting your actual account and PHP path. The locks are required: deployments wait for running jobs, and cron skips new work during migration. This bounded queue worker suits shared hosting; continuously supervised workers need equivalent stop/drain coordination before using this deployment script.

```cron
* * * * * /usr/bin/flock -n /home/ACCOUNT/metrial/queue.lock /bin/sh -c 'cd /home/ACCOUNT/metrial/current && /opt/cpanel/ea-php85/root/usr/bin/php artisan queue:work redis --stop-when-empty --max-time=50 --timeout=60 --tries=5' >> /home/ACCOUNT/metrial/shared/storage/logs/queue-cron.log 2>&1
* * * * * /usr/bin/flock -n /home/ACCOUNT/metrial/scheduler.lock /bin/sh -c 'cd /home/ACCOUNT/metrial/current && /opt/cpanel/ea-php85/root/usr/bin/php artisan schedule:run' >> /home/ACCOUNT/metrial/shared/storage/logs/scheduler-cron.log 2>&1
```

Set `TRUSTED_PROXIES` in Laravel's server environment to the comma-separated IP addresses or CIDRs of the proxies that connect to the application. The default trusts only loopback. For Docker behind a host TLS proxy, include the host gateway address seen by the web container. Configure the proxy to supply accurate `X-Forwarded-For` and `X-Forwarded-Proto` headers; do not trust arbitrary public clients or use `*`. This keeps IP-based authentication limits separate for different clients.

Deployment validates the archive checksum, runtime configuration, and database/Redis connectivity before entering maintenance. It then takes a compressed MySQL snapshot, runs forward migrations and `DatabaseSeeder` for roles, permissions and location reference data, verifies deployment readiness, switches `current`, restarts Passenger, and checks both HTTPS health endpoints. Production seeding does not create a default administrator. Expect a maintenance window. Failed backups, migrations or seeding leave the old release selected and maintenance enabled. A failed health check after activation re-enables maintenance and preserves `previous` for recovery. Inspect the failure before rerunning; failed releases are retained deliberately.

Use **Actions → cPanel rollback** with an existing release ID (`commitSHA-runID-attempt`) to reactivate compatible old code. It preserves shared secrets/storage and never reverses migrations. Database changes must follow an expand/contract rollout for code rollback to work. Database restoration is a separate deliberate recovery operation; rehearse it, retain off-host backups, and rotate old release archives/backups according to your retention policy. No cPanel account or GitHub environment secrets have been configured by this repository change.


## دومين روابط التطبيق

راجع [إعداد الروابط وملفات association](../Shared/deep-links.md). دومين الروابط يستخدم Laravel public وHTTPS، ومعرفات Android/iOS الحقيقية. ملفات `.well-known` لا تمر عبر Socket.IO ولا redirects. ميزة الإحالات تضيف migration للجداول ونافذة ربط الحساب الجديد؛ الحسابات القديمة لا تُمنح نافذة بأثر رجعي. لا توجد مكافآت نقاط في هذه المرحلة.
