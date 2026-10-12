# الفلاتر والبحث والترتيب في القوائم

[الفهرس](../README.md) · [عقد HTTP](http-contract.md) · [كل المسارات](../ENDPOINTS.md)

القوائم تدعم معاملات محددة لكل مورد. الفلاتر تطبّق داخل نطاق الصلاحيات الحالي: العميل يرى بياناته، والبيانات العامة تظل خاضعة لقواعد النشر والتفعيل. لا توجد فلاتر على كلمات المرور أو التوكنات أو بيانات الدفع المشفّرة.

## طريقة الاستخدام

1. اختر endpoint من الجدول أدناه، وأرسل توكن الحساب إذا كان مطلوبًا.
2. أرسل الفلاتر المناسبة فقط؛ المعامل غير المدعوم يرجع `422 VALIDATION_FAILED` بدل تجاهله. `locale` يظل مدعومًا.
3. اختر ترتيبًا بإحدى الصيغتين، ثم أرسل `page` و`per_page` للقوائم المرقمة فقط.
4. عند تغيير البحث أو الفلاتر أو الترتيب، أعد `page=1`. احتفظ بنفس المعاملات عند الانتقال للصفحة التالية.
5. استخدم `data.meta.total` لعدد النتائج المطابقة، و`last_page` لنهاية الصفحات. عدم وجود نتائج يرجع `200` بقائمة فارغة.

### الترتيب

```http
GET /api/v1/admin/users?type=client&sort=name&sort_type=asc
GET /api/v1/admin/users?type=client&sort=-created_at,name
```

- `sort_type`: `asc` أو `desc`، مع حقل `sort` واحد دون علامة `-`.
- `sort=-created_at,name`: الأحدث أولًا، ثم الاسم تصاعديًا. لا ترسل `sort_type` في هذه الصيغة.
- حتى 3 حقول مختلفة من الحقول المسموحة فقط. الحقول والعلاقات وعبارات SQL غير المدرجة مرفوضة.
- عند عدم تحديد `id` يضاف تلقائيًا بنفس اتجاه أول حقل لفضّ التعادل.
- القيم `null` في حقول الترتيب الاختيارية تأتي أخيرًا في الاتجاهين.
- دون `sort` يظل ترتيب المورد الافتراضي كما هو. راجع جدول الموارد.
- `sort_type=desc` وحده أو `sort=-id&sort_type=asc` يرجع `422`.
- الترتيب الثابت لا يجمّد البيانات: pagination بنظام offset قد يتأثر بإضافة أو حذف سجلات بين الطلبات.

### الفلاتر المشتركة

| المعامل | الاستخدام |
|---|---|
| `search` | نص من 1 إلى 100 حرف، على الحقول المعلنة للمورد فقط |
| `page` | عدد صحيح يبدأ من 1، للقوائم المرقمة |
| `per_page` | 1–100، افتراضي 25 للقوائم المرقمة |
| `created_from` / `created_to` | تاريخ `YYYY-MM-DD` بتوقيت UTC على الموارد التي تدعمه |
| `amount_min` / `amount_max` | مبالغ صحيحة بوحدة العملة الصغرى، حدود شاملة |
| `balance_min` / `balance_max` | رصيد صحيح بوحدة العملة الصغرى؛ لمحافظ الأدمن |
| `currency` | رمز من 3 أحرف إنجليزية كبيرة، مثل `EGP` |

تاريخ النهاية يشمل اليوم كاملًا. مثال `created_to=2026-10-11` يطابق ما قبل `2026-10-12 00:00:00 UTC`. النهاية الأقل من البداية أو تاريخ غير صالح يرجع `422`.

يجب تحديد عملة واحدة عند تصفية `amount`/`balance` أو ترتيبها في قوائم المدفوعات والمحافظ؛ تاريخ محفظة واحدة له عملة محددة بالفعل. مثال `1500` يساوي `15.00 EGP`؛ راجع [وحدات الأموال](payment-providers.md).

الفلاتر المختلفة تتجمع باستخدام AND. حقول `status` متعددة القيم حيث يذكر الجدول ذلك، و`type` للمستخدمين، تقبل قيمة واحدة أو قائمة حتى 20 قيمة مختلفة:

```http
GET /api/v1/admin/users?type[]=client&type[]=vendor&status[]=active&status[]=pending
```

داخل القائمة يستخدم OR. القوائم الفارغة، المفاتيح الترابطية والقيم غير المسموحة مرفوضة. البوليان يرسل `1` أو `0` في query؛ `0` فلتر فعلي ولا يتم تجاهله.

البحث النصي يستخدم substring حرفي؛ `%` و`_` و`!` لا توسّع النتائج كـwildcards. البحث في الهاتف، reference، ورموز المواقع يبقى بالمطابقة الكاملة حيث هو موضح. الصفحات العامة تبحث في العنوان المنشور والـslug، ولا تبحث في المسودة.

البحث في اسم موقع/تصنيف أو عنوان مسودة يفحص الترجمات بصرف النظر عن لغة الواجهة؛ لغة الواجهة تحدد العرض والترتيب مع fallback الترجمات. ترتيب النص في قاعدة البيانات يتبع collation الخاصة بها. الصفحات العامة الصغيرة تُرتّب في الذاكرة على عنوان النسخة المنشورة؛ المقارنة ليست ترتيبًا لغويًا معتمدًا على ICU.

## الموارد المدعومة

جميع المسارات أدناه بعد `/api/v1`. `created` يعني دعم `created_from` و`created_to`. الحقول تحت «الترتيب» تدعم التصاعدي والتنازلي.

| القائمة | الفلاتر والبحث | حقول الترتيب | الافتراضي / الاستجابة |
|---|---|---|---|
| `admin/users` | `search` الاسم/البريد، الهاتف كامل؛ `type` و`status` قيمة أو قائمة؛ `phone_country_id`, `phone_verified`, created | `id,name,created_at,last_login_at` | `-id`؛ مرقمة |
| `admin/payments` | `status` قيمة/قائمة، `provider`, `currency`, `amount_min/max`, `user_id`, created | `id,created_at,amount` | `-id`؛ مرقمة |
| `client/payments`, `vendor/payments` | نفس فلاتر الدفع عدا `user_id`؛ ملكية الحساب تلقائية | `id,created_at,amount` | `-id`؛ مرقمة |
| `admin/wallets` | `user_id,currency,is_locked,balance_min,balance_max` | `id,currency,balance` | `-id`؛ مرقمة |
| `client/wallet`, `vendor/wallet` | `currency` | `id,currency,balance` | `currency`؛ مصفوفة كاملة |
| `{client\|vendor}/wallet/{wallet}/transactions` | `direction=credit\|debit`, `reason` كامل، `amount_min/max`, created | `id,created_at,amount` | `-id`؛ مرقمة؛ المحفظة مملوكة للحساب |
| `admin/contact-messages` | `status=new\|reviewed\|closed` قيمة/قائمة؛ `search` subject جزئي أو reference/phone كامل؛ created | `id,created_at,updated_at` | `-id`؛ مرقمة |
| `client/support/conversations` | `status=new\|in_progress\|resolved\|closed` قيمة/قائمة؛ `search` في subject؛ created | `id,created_at,updated_at` | `-updated_at,-id`؛ مرقمة |
| `admin/support/conversations` | نفس فلاتر المحادثة + `user_id,assigned_to,unassigned` | `id,created_at,updated_at` | `-updated_at,-id`؛ مرقمة |
| `categories` | `search` أو alias `keyword` للاسم؛ `parent_id`, `root_only` | `id,sort_order,name` | `sort_order,id`؛ مرقمة |
| `admin/categories` | فلاتر التصنيفات + `is_active` | `id,sort_order,name` | `sort_order,id`؛ مرقمة |
| `locations/countries` | `search` اسم أو ISO/calling_code كامل؛ `iso2,calling_code` | `id,sort_order,name,iso2,iso3,calling_code` | `sort_order,iso2`؛ مصفوفة كاملة |
| `admin/locations/countries` | نفس فلاتر الدول + `is_active` | نفس حقول الدول | نفس ترتيب الدول؛ مصفوفة كاملة |
| `locations/countries/{country}/governorates` | `search` الاسم أو code كامل؛ `code,type` | `id,sort_order,name,code` | `sort_order,id`؛ مصفوفة كاملة |
| `admin/locations/governorates` | نفس فلاتر المحافظات + **`country_id` مطلوب** و`is_active` | نفس حقول المحافظات | `sort_order,id`؛ مصفوفة كاملة |
| `locations/governorates/{governorate}/cities` | `search` الاسم أو code/postal_code كامل؛ `code,postal_code` | `id,sort_order,name,code` | `sort_order,id`؛ مصفوفة كاملة |
| `admin/locations/cities` | نفس فلاتر المدن + **`governorate_id` مطلوب** و`is_active` | نفس حقول المدن | `sort_order,id`؛ مصفوفة كاملة |
| `admin/pages` | `search` عنوان/slug؛ `status=draft\|published\|archived` قيمة/قائمة؛ `show_in_footer,show_in_settings`, created | `id,sort_order,created_at,updated_at,title,slug` | `sort_order,id`؛ مرقمة |
| `pages` | `search` عنوان/slug المنشور؛ `slug,show_in_footer,show_in_settings` | `id,sort_order,title,slug,published_at` | `sort_order,id`؛ مصفوفة منشورة كاملة |
| `admin/pages/{page}/revisions` | `version,published_by,published_from,published_to` | `id,version,published_at` | `-version`؛ مرقمة |
| `{admin\|client\|vendor}/auth/sessions` | `search` اسم الجهاز؛ `is_current`, created و`last_used_from/to` | `id,created_at,last_used_at` | `-id`؛ `data.items` كاملة للجلسات الصالحة |
| `{admin\|client\|vendor}/notifications` وضع العرض | `status=read\|unread\|all`, `category=security\|transactional\|marketing`, created | `id,created_at` | `-created_at,-id`؛ مرقمة |
| `client/referrals/history` | `id`, created؛ دون تفاصيل المدعو | `id,created_at` | `-id`؛ مرقمة |
| `admin/integrations/myfatoorah/operations` | `operation,status,customer_id,actor_id`, created؛ status قيمة/قائمة من `ready,sending,succeeded,rejected,uncertain,confirmed_succeeded,confirmed_failed` | `id,created_at,updated_at` | `-id`؛ مرقمة |
| `admin/integrations/myfatoorah/entities` | `kind,status,reference,customer_id,needs_refresh`, created و`synced_from/to`؛ `search` reference كامل | `id,created_at,updated_at,synced_at` | `-id`؛ مرقمة |

`root_only=1` يعني `parent_id IS NULL`؛ `root_only=0` يعني تصنيفات لها أب. إرسال `parent_id` مع `root_only` متعارض يرجع 422. `parent_id=` يبقى alias قديم للجذور. `keyword` و`search` مع قيمتين مختلفتين مرفوضان.

`unassigned=1` يعني محادثات بلا موظف؛ `unassigned=0` يعني محادثات مسندة. لا تجمع `unassigned=1` مع `assigned_to`. `phone_verified=0` يطابق المستخدمين دون تاريخ توثيق.

## استثناءات المزامنة

- إشعارات `since_version`: ترتيب ثابت `revision,id`، مع tombstones وsnapshot. المسموح `since_version,until_version,cursor,per_page,locale`. لا تجمع معها status/category/date/sort/sort_type/page. إرسال cursor أو until_version دون since_version يرجع 422. `unread_count` دائمًا عدد غير المقروء في الحساب كله، وليس نتائج الفلتر.
- رسائل الشات: `after_id,per_page,locale` فقط، ترتيب ID تصاعدي و`next_after_id/has_more` كما كان. الحجم الافتراضي 50 والحد الأعلى 100. لا يوجد بحث في نص الرسائل ضمن بروتوكول المزامنة.
- قوائم MyFatoorah الخارجية تحافظ على عقد المزود؛ هذه الفلاتر تخص سجلات operations/entities المحلية فقط.
- config، الملخصات، الصلاحيات، preferences، وshow endpoints ليست قوائم سجلات قابلة للتصفية. لا توجد قائمة مشاركة جديدة ولا فلاتر نقاط؛ المكافآت ما زالت غير مفعلة.
- لا ترسل page/per_page للقوائم الكاملة؛ لم يتحول شكل استجابتها إلى pagination.

## أمثلة طلب واستجابة

الأرقام والبيانات توضيحية مطابقة للعقد، وليست بيانات مستخدمين حقيقية. اضبط `API_BASE` على عنوان بيئتك المنتهي بـ `/api/v1`.

```sh
curl --get "$API_BASE/client/payments" \
  -H "Authorization: Bearer $CLIENT_TOKEN" \
  -H 'Accept: application/json' -H 'X-Locale: en' \
  --data-urlencode 'status[]=paid' \
  --data-urlencode 'status[]=pending' \
  --data-urlencode 'currency=EGP' \
  --data-urlencode 'amount_min=1000' \
  --data-urlencode 'created_from=2026-10-01' \
  --data-urlencode 'created_to=2026-10-11' \
  --data-urlencode 'sort=amount' --data-urlencode 'sort_type=desc' \
  --data-urlencode 'page=1' --data-urlencode 'per_page=10'
```

HTTP 200، مثال سجل واحد مدفوع:

```json
{
  "success": true,
  "message": null,
  "data": {
    "items": [{
      "id": 42,
      "uuid": "0199d000-0000-7000-8000-000000000042",
      "provider": "stripe",
      "amount": 1500,
      "currency": "EGP",
      "status": "paid",
      "refunded_amount": 0,
      "created_at": "2026-10-11T10:00:00+00:00"
    }],
    "meta": {"current_page": 1, "last_page": 1, "per_page": 10, "total": 1}
  }
}
```

```http
GET /api/v1/client/wallet?currency=EGP&sort=id
```

```json
{"success":true,"message":null,"data":[{"id":7,"currency":"EGP","balance":1500,"is_locked":false}]}
```

```http
GET /api/v1/admin/users?type=client&status=active&search=Ahmed&sort=-created_at,id&per_page=25
GET /api/v1/categories?locale=ar&search=كتب&root_only=1&sort=name&sort_type=asc
GET /api/v1/admin/support/conversations?status[]=new&status[]=in_progress&unassigned=1&sort=-updated_at
```

إرسال حقل غير مسموح، مثل `GET /api/v1/client/payments?user_id=8`، يرجع HTTP 422:

```json
{
  "success": false,
  "code": "VALIDATION_FAILED",
  "message": "The given data was invalid.",
  "errors": {"user_id": ["This query parameter is not supported for this list."]}
}
```

## إضافة قائمة جديدة في الباك إند

استخدم Form Request مناسبًا في Admin/Client/Shared يرث `ListRequest`، وحدد `SORTS`, `DEFAULT_SORT`, `FILTERS` وما يحتاجه المورد من SEARCH/DATES/RANGES. للحقول الافتراضية التي يعالجها controller فقط استخدم `VIRTUAL_FILTERS`. لا تضف كل أعمدة الموديل تلقائيًا ولا تستخدم مدخل المستخدم كاسم عمود.

ابدأ باستعلام يفرض الصلاحيات والملكية/التفعيل، ثم `ListQuery::apply()`، ثم `paginate($request->perPage())` أو `get()` وفق العقد. لا تضف trait لكل الموديلات؛ سجلات التدقيق والـoutbox وبيانات الاعتماد ليست واجهات تصفية عامة. توثيق OpenAPI يستخرج عقود هذه Requests، والاختبارات تغطي العزل والترتيب والتعارضات.

لم تُضف أعمدة أو indexes دون دليل احتياج. عند تحسين استعلام كثيف في بيئة حقيقية، استخدم EXPLAIN على فلاتره الفعلية وبيانات ممثلة قبل إضافة index؛ البحث LIKE والترتيب المترجم قد يحتاجان معالجة أداء عند نمو البيانات.

## البحث العربي مثل برق

يطبق البحث بعد فلاتر الحالة والتاريخ والملكية وقبل pagination، بالتدرّج التالي:

1. مطابقة النص حرفيًا (بحسب collation قاعدة البيانات). إن وُجدت نتائج، لا يتم توسيع البحث.
2. عند غياب النتائج وكان الاستعلام يحتوي حروفًا عربية: تجاهل التشكيل والتطويل والمسافات والمحارف الخفية، وتوحيد `أ/إ/آ/ٱ → ا`، `ى/ی/ئ → ي`، `ؤ → و`، `ک → ك`.
3. إن لم توجد نتائج أيضًا، يُسمح بمطابقة `ة/ه`.

في الجداول المترجمة، المرحلة الثانية والثالثة تعملان على صفوف `locale=ar` فقط. `Icecream` لا يطابق `Ice Cream`، حتى مع واجهة عربية. البحث العربي يعمل أيضًا مع `locale=en`، وتظل النتيجة معروضة باللغة المطلوبة. أسماء المستخدمين ومواضيع رسائل التواصل والدعم تستفيد من نفس التدرّج داخل نطاقها المسموح. بيانات العرض المخزنة لا تتغير بسبب البحث.

أمثلة:

```http
GET /api/v1/categories?search=قهوه%20ابراهيم%20اللبناني&locale=en
GET /api/v1/categories?search=فول%20و%20تميس&per_page=١٠
GET /api/v1/client/support/conversations?search=استفسار%20عن%20الطلب%20١٢٣
```

الأول يمكن أن يطابق `قَهْوَةُ إِبْرَاهِيم اللبنانى` عند غياب المطابقة الحرفية. الثاني يمكن أن يطابق `فول وتميس`. نتيجة حرفية مخفية أو مملوكة لحساب آخر لا تمنع نتائج fallback المسموحة. البحث بالتشكيل أو التطويل وحدهما يرجع قائمة فارغة.

SQLite يستخدم دالة اتصال لتنفيذ نفس قواعد التطبيع، لتجنب حد عمق محلل SQL؛ MySQL 8 يستخدم REGEXP_REPLACE وREPLACE. هذا البحث لا يعتمد على محرك full-text ولا يتطلب أعمدة إضافية؛ طبّق EXPLAIN قبل توسيعه لبيانات كبيرة.
