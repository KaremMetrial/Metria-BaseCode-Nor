# التحقق والاختبارات

[الفهرس](../README.md)

## Verification

```sh
php artisan test --compact
vendor/bin/pint --dirty --format agent
composer validate --strict
composer audit
npm audit
npm --prefix socketio audit
npm run build
docker compose config --quiet
node --test socketio/auth.test.cjs
```

SQLite skips five MySQL multi-process concurrency tests and one opt-in Redis integration test. To exercise those tests, use a disposable MySQL database with the full `DB_*` environment, set `RUN_REDIS_INTEGRATION=1` and isolated `REDIS_*` settings, then run the same Laravel suite. Tests migrate/reset the supplied test database. Set `TEST_REDIS_PORT` to a disposable Redis port to run `node --test socketio/auth.test.cjs socketio/integration.test.cjs`.

Larastan/PHPStan runs at level 5 across application, database and route code with no ignored-error baseline: `composer analyse`. The GitHub Actions workflow runs the MySQL/Redis suite, static analysis, audits, build and Socket.IO integration tests; hosted execution still requires the repository to be connected to GitHub.


هذه أوامر للمطور عند تغيير السلوك. تحديث التوثيق وحده يحتاج مراجعة المسارات والروابط وصحة JSON والأمثلة، ولا يستلزم اتصالًا حيًا بمزود SMS أوالدفع. لا تستخدم قاعدة بيانات عملاء لتشغيل الاختبارات.

## اختبار Laravel إلى Socket.IO

اختبار `RedisQueueIntegrationTest::test_laravel_token_and_queued_notifications_reach_socket_io` يصدر التوكن من REST بالتوثيق الحقيقي، ويشغّل `socketio/app.js` وعميل Node، ثم يمرر إشعار Outbox عبر أمر النشر وعامل Redis. يتحقق من وصول notification.created وnotification.state_changed وتطابق صندوق REST، ويغلق العمليات بعد الاختبار.

بعد تثبيت اعتماديات `socketio`، استخدم **Redis مخصصًا للاختبارات** وبيئة قاعدة بيانات اختبارية:

```sh
RUN_REDIS_INTEGRATION=1 RUN_SOCKET_INTEGRATION=1 \
REDIS_HOST=127.0.0.1 REDIS_PORT=16379 REDIS_PASSWORD=null \
REDIS_PREFIX=metrial_socket_test_ REDIS_DB=0 REDIS_CACHE_DB=1 \
vendor/bin/phpunit tests/Feature/Notifications/RedisQueueIntegrationTest.php
```

16379 مثال لمنفذ Redis المعزول؛ استبدله بمنفذ الخدمة الاختبارية. اختبار Socket يستعمل المنفذ16096 افتراضيًا، ويمكن تغييره عبر TEST_LARAVEL_SOCKET_PORT. لا تشغل اختبارات DatabaseMigrations على قاعدة المشروع الفعلية. CI يثبت اعتماديات Node قبل اختبارات PHP ويفعّل الاختبارين.
