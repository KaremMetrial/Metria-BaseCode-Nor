# أوامر التشغيل والمتابعة

[الفهرس](../README.md) · [Outbox](../Shared/outbox-and-idempotency.md)

نفّذ الأوامر داخل runtime التطبيق (`docker compose exec app ...` عند Docker)، لا في بيئة PHP تختلف عن الإنتاج. help يوضح المعاملات الفعلية.

| الأمر | متى يستخدم | الأثر |
|---|---|---|
| `php artisan route:list --except-vendor` | مراجعة المسارات | قراءة التسجيل الحالي |
| `php artisan schedule:list` | فحص الجدولة | عرض المهام القادمة |
| `php artisan notifications:publish-pending` | إعادة دفع عمل معلق | يرسل jobs للـqueue، ليس قراءة فقط |
| `php artisan payments:reconcile --payment=42` | دفعة غير محسومة | يقرأ المزود ويطبق التسوية المؤكدة؛ لا ينشئ تحصيلًا/استردادًا جديدًا |
| `php artisan payments:reconcile --limit=25` | دفعة تشغيل دورية | حتى 25 سجلًا، النطاق 1–100 |
| `php artisan media:import-avatars` | انتقال الصور القديمة | يسجل الملفات القديمة دون نقلها أوإعادة ترميزها |
| `php artisan media:prune --limit=500` | تنظيف الملفات غير المستخدمة/المحذوفة | حذف فعلي قابل لإعادة المحاولة؛ ليس تنظيف تاريخ الرسائل |
| `php artisan app:readiness` | مراقبة البيئة | نتيجة وفحوص تشغيل، exit غيرصفر عند فشل |
| `php artisan app:readiness --configuration-only` | مراجعة إعدادات فقط | يتجاوز probes قاعدة البيانات وRedis |
| `php artisan app:readiness --deployment` | أثناء النشر | فحص الإعداد والاتصال دون الاعتماد على جداول التطبيق/backlog |
| `php artisan app:readiness --sandbox-payments` | staging | يتطلب وضع دفع sandbox بدل live |
| `php artisan queue:failed` | استكشاف تعثر العمل | قراءة jobs الفاشلة؛ عالج السبب قبل retry |

## الجدولة الحالية

- كل دقيقة: notifications:publish-pending، دون overlap.
- كل 5 دقائق: payments:reconcile --limit=25، دون overlap.
- كل 10 دقائق: media:prune، دون overlap.
- يوميًا: sanctum:prune-expired --hours=24.

يجب تشغيل scheduler وعامل queue معًا. في النشر التقليدي schedule:run كلدقيقة وعامل تحت supervision؛ cPanel يستخدم [آلية cron والـlocks الموثقة](deployment.md). مراقبة queue وحدها لا تكشف تراكم outbox لم يُرسل بعد.

## قراءة النتيجة

readiness لا يطبع الأسرار ولا يثبت نجاح SMS/FCM/الدفع الحقيقي أوالنسخ الاحتياطية أوسعة الإنتاج. التسوية التي لا تجد دليلًا حاسمًا قد تبقى معلقة وتخرج برمز غيرصفر. احتفظ بمعرف الدفعة/العملية وrequest ID، وافحص المزود قبل أي إجراء مالي جديد.

مصادر: [console.php](../../routes/console.php)، [Commands](../../app/Console/Commands).
