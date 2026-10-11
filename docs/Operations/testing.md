# التحقق والاختبارات

[الفهرس](../README.md)

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


هذه أوامر للمطور عند تغيير السلوك. تحديث التوثيق وحده يحتاج مراجعة المسارات والروابط وصحة JSON والأمثلة، ولا يستلزم اتصالًا حيًا بمزود SMS أوالدفع. لا تستخدم قاعدة بيانات عملاء لتشغيل الاختبارات.
