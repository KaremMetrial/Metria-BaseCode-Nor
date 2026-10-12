# تشغيل المشروع محليًا

[الفهرس](../README.md) · [الإعدادات](configuration.md)

هذا القسم لإعداد بيئة جديدة. لا تشغّل أوامر إعادة إنشاء قاعدة البيانات على بيانات قائمة. التطبيق يحتاج PHP8.5 ونسخ الحزم المثبتة في composer.lock/package-lock.json.

## Local setup

Install Composer and npm dependencies, copy `.env.example` to `.env`, and generate `APP_KEY` with `php artisan key:generate`. Configure a random `SOCKET_TOKEN_SECRET` of at least 32 bytes before starting Socket.IO; Laravel and Socket.IO must share it. `openssl rand -hex 32` generates a suitable value.

```sh
composer install
npm ci
npm --prefix socketio ci
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

## بيانات تجريبية لكل الميزات

لإنشاء بيانات مترابطة على قاعدة التطوير بعد تشغيل migrations:

```sh
php artisan db:seed --class=DemoSeeder --no-interaction
# داخل Docker:
docker compose exec app php artisan db:seed --class=DemoSeeder --no-interaction
```

اختر الأمر المناسب لبيئتك؛ لا تحتاج إلى حذف قاعدة البيانات أو `migrate:fresh`. يبدأ `DemoSeeder` ببيانات الأساس الموجودة ثم يشغّل الميزات بترتيب الاعتماديات. يبقى `php artisan db:seed` بدون `--class` مخصصًا لبيانات الأساس. جميع Demo seeders ترفض التشغيل خارج `local` و`development` و`testing` حتى مع `--force`.

| Seeder | البيانات على قاعدة جديدة |
| --- | --- |
| `DatabaseSeeder` | الصلاحيات والأدوار، الدول والمحافظات والمدن، الصفحات الأولية، إعدادات التواصل، أدمن التطوير |
| `DemoContentSeeder` | 4 تصنيفات عربي/إنجليزي، منها أبناء وتصنيف مخفي وأسماء لتجربة البحث العربي؛ محتوى أولي منشور للصفحات الخمس، صفحة `demo-help` منشورة وصفحة `demo-draft` مسودة؛ بيانات التواصل |
| `DemoUserSeeder` | عميل مكتمل، عميل للإحالة، عميل onboarding، عميل محظور، Vendor نشط وآخر قيد المراجعة، موظف دعم وموظف مالية؛ قبول الشروط للملفات المكتملة |
| `DemoSupportSeeder` | 3 رسائل تواصل: new/reviewed/closed؛ 4 محادثات: new/in_progress/resolved/closed، و7 رسائل مترابطة مع الموظف ومؤشرات القراءة |
| `DemoFinanceSeeder` | محافظ العميل والبائع، credit/debit، 8 مدفوعات موزعة على paid/failed/partially_refunded/refunded، و4 استردادات؛ محفظة مقفلة للعميل المحظور |
| `DemoNotificationSeeder` | إشعارات مقروءة وغير مقروءة ومؤرشفة للأدمن والعميل والبائع والدعم والمالية، مع sequence/revision وحالات تسليم skipped |
| `DemoReferralSeeder` | كود إحالة، إحالة مستخدم فعلي مرتبط، وروابط مشاركة home/page/category |
| `DemoMediaSeeder` | 6 ملفات PNG فعلية على القرص الخاص `local`: 3 صور حسابات، مرفق شات، صورة صفحة منشورة، ورفع مؤقت غير مرتبط |
| `DemoIntegrationSeeder` | 7 snapshots و7 سجلات عمليات محلية لـMyFatoorah: invoice/session/subscription/supplier/refund/shipment/transfer |

كل Seeder خاص بميزة يمكن تشغيله منفردًا بنفس `--class` بعد تشغيل المجموعة كاملة مرة واحدة. ترتيب القائمة أعلاه هو ترتيب الاعتماديات. النموذج ينشئ أيضًا سجلات Audit من نشر الصفحات وتسوية الاستردادات وقبول الإحالة.

### الحسابات وطريقة الدخول

| الحساب | هوية الدخول | الحالة |
| --- | --- | --- |
| الأدمن الرئيسي | قيمة `DEV_ADMIN_EMAIL`، افتراضيًا `admin@example.com` | Super admin |
| الدعم | `support@demo.example.test` | صلاحيات support-agent |
| المالية | `finance@demo.example.test` | صلاحيات finance-admin |
| العميل | `+201000000101` | ملف مكتمل، محفظة وشات وإشعارات وروابط |
| العميل المُحال | `+201000000102` | ملف مكتمل، مرتبط بإحالة العميل الأول |
| عميل onboarding | `+201000000103` | هاتف موثق، الملف لم يكتمل |
| العميل المحظور | `+201000000104` | blocked، محفظة مقفلة |
| البائع | `+201000000201` | active |
| البائع قيد المراجعة | `+201000000202` | pending |

كلمة مرور الحسابات الإدارية هي قيمة `DEV_ADMIN_PASSWORD`، افتراضيًا `password` للتطوير. إعادة seeding لا تعيد تعيين كلمات المرور أو الأدوار الموجودة. العملاء لا يملكون بريدًا أو كلمة مرور؛ يستخدم العميل والبائع تدفق الهاتف/OTP الموثق في [دليل العميل](../Client/authentication.md) و[دليل البائع](../Vendor/authentication.md). الأرقام أعلاه أمثلة للتطوير؛ اختبار SMS الفعلي يحتاج أرقام اختبار معتمدة عند المزود.

الـseeder لا ينشئ OTP ثابتًا أو جلسات دخول أو FCM tokens وهمية، ولا يتجاوز التحقق. هذه البيانات تصدر من تسجيل الدخول وتسجيل الجهاز الحقيقيين. إعدادات اللغات والتطبيق ومزودي SMS والدفع وSocket.IO وروابط المتاجر تأتي من config وبيئة التشغيل، وليس من بيانات وهمية في قاعدة البيانات.

### إعادة التشغيل وحدود الأمثلة

- إعادة الأمر بنفس هويات البيانات لا تكرر الحسابات أو الرسائل أو الحركات أو الاستردادات ولا تعيد شحن الرصيد. بعد التشغيل الأول يكون رصيد العميل والبائع **127500 وحدة صغرى** لكل محفظة، وفق `payments.default_currency`؛ مع EGP يساوي **1275.00**. يتم تكوين الرصيد عبر `WalletService` مع دفتر الحركات، وليس بتعديل balance مباشرة.
- الهوية الثابتة للحساب التجريبي هي الهاتف أو البريد الموضح أعلاه، وللتصنيف الاسم الإنجليزي `Demo …`. لا تستخدم هذه الهويات لحسابات أخرى؛ تغييرها ثم إعادة seeding ينشئ هوية تجريبية بديلة. التغييرات في اسم المستخدم والرصيد وحالة المحادثة تبقى كما هي.
- الصفحات الأساسية تُملأ وتنشر فقط إذا كانت فارغة ولم يسبق نشرها؛ أي محتوى حرّره الأدمن يُترك كما هو. جميع النصوص القانونية المولدة موضّح أنها تجريبية. إعدادات تواصل غير فارغة لا تُستبدل.
- مدفوعات الأمثلة تحمل `provider=demo`، وهو ليس بوابة دفع مسجلة. تصلح لعرض القوائم وتصفية الحالة/المبلغ، ولا تدعم فلتر `provider=demo` أو checkout أو استردادًا خارجيًا. اختبر دورة الدفع الحقيقية بمدفوعات جديدة عبر مزود sandbox مضبوط.
- سجلات MyFatoorah تحمل `demo` في snapshot/result ومراجع `demo-*`؛ لا تمثل موارد لدى المزود. وظائف التقارير والبحث الخارجي وtokenization تتطلب sandbox؛ لا تُنشأ بطاقات أو توكنات دفع وهمية.
- لا يرسل seeding أي SMS أو بريد أو Push، ولا يجدول عمليات خارجية. الإشعارات أمثلة تاريخية منشورة مسبقًا وتسليمها skipped؛ أحداث Outbox الجديدة تتولد من استخدام الميزات عبر API. اختبار Socket.IO والـqueue يتطلب الخدمات الفعلية كما في [دليل realtime](../Shared/realtime-protocol.md).
- لا تُمنح نقاط إحالة ولا أرصدة مكافآت؛ سياسة استحقاق واستبدال النقاط ما زالت غير محددة.
- الملفات الخاصة بالـDemo توجد تحت `storage/app/private/demo/`؛ الرفع المؤقت يخضع لتنظيف الميديا المعتاد. بقية الملفات مرتبطة بالحسابات والصفحة والمحادثة، وتُقرأ من endpoints الميديا وصلاحياتها المعتادة.

### تجربة سريعة

بعد الدخول بهوية العميل الأول واستخدام Bearer token الناتج:

```http
GET /api/v1/client/profile
GET /api/v1/client/wallet
GET /api/v1/client/payments?status=partially_refunded
GET /api/v1/client/notifications?status=unread
GET /api/v1/client/support/conversations?status=in_progress
GET /api/v1/client/referrals
```

يمكن تجربة البحث العام بـ`GET /api/v1/categories?search=قهوه%20ابراهيم` وفتح `GET /api/v1/pages/demo-help`. راجع [فهرس endpoints](../ENDPOINTS.md) لعقود الاستجابة، والدليل الخاص بكل ميزة لاختبار إنشاء أو تعديل بيانات جديدة.
