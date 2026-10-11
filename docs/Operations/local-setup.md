# تشغيل المشروع محليًا

[الفهرس](../README.md) · [الإعدادات](configuration.md)

هذا القسم لإعداد بيئة جديدة. لا تشغّل أوامر إعادة إنشاء قاعدة البيانات على بيانات قائمة. التطبيق يحتاج PHP8.5 ونسخ الحزم المثبتة في composer.lock/package-lock.json.

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

The financial migrations create ledger immutability triggers. On MySQL with binary logging, use a database administrator connection for migration when the application user lacks the required privilege; restore the ordinary application connection afterward.

The example DB credentials and development admin are for local use. Set unique secrets, `APP_ENV=production`, `APP_DEBUG=false`, and TLS before a deployment. The development admin is only seeded in local/development/testing. Do not run `migrate:fresh` against an existing application database.

Services: `app`, `web`, `db`, `redis`, `queue`, `scheduler`, `socketio`. Host DB/Redis ports bind to loopback. The scheduler republishes pending notification outbox entries; both scheduler and queue worker must run. Redis uses `noeviction` to protect queued work: monitor memory and failed jobs. Docker configuration validation is recorded in the audit; a complete production deployment was not performed.


## تجهيز الميزات

الميديا تحتاج GD للصور وClamAV للـPDF: [الإعداد](../Shared/media-storage.md). الصفحات الأولية مسودات وتحتاج النشر. اضبط SMS قبل تجربة OTP، والدفع قبل شحن المحفظة، وFCM إذا احتجت Push خارجيًا.

`GET /up` يختبر حياة Laravel؛ لا يكفي وحده لإثبات اتصال المزود أوالـqueue. استخدم app:readiness مع فهم [حدوده](commands.md).
