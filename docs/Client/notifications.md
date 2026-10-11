# Client — صندوق الإشعارات والمزامنة

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اعرض القائمة أو unread-count واحفظ snapshot_version.
2. قراءة إشعار وأرشفته عمليتان قابلتان للتكرار.
3. لقراءة الجميع أرسل through_version من القائمة؛ الإشعارات الأحدث تبقى غير مقروءة.
4. للمزامنة أرسل since_version، ثم ثبّت until_version على snapshot_version الراجع، وواصل cursor حتى يصبح next_cursor=null.
5. ارفع مؤشر المزامنة المحلي فقط بعد استهلاك جميع الصفحات، وطبّق archived_at كعلامة حذف من الصندوق.

ادمج بالـid وrevision. في وضع المزامنة يُرفض إرسال فلاتر الصندوق status/category بخطأ 422. الإشعارات القديمة قد تحتفظ بلغتها وقت الإنشاء. لا يوجد endpoint لإرسال إشعار عشوائي من التطبيق.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/client/notifications/unread-count` |
| `PATCH` | `/api/v1/client/notifications/read-all` |
| `PATCH` | `/api/v1/client/notifications/{notification}/archive` |
| `GET` | `/api/v1/client/notifications` |
| `PATCH` | `/api/v1/client/notifications/{notification}/read` |

## أمثلة التدفق بالتفصيل

### 8.1 عرض الصندوق — GET /notifications

```sh
curl --fail-with-body -X GET "$CLIENT_BASE/notifications?status=unread&category=security&per_page=25&page=1" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "items": [
      {
        "id": "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa",
        "category": "security",
        "action": {
          "screen": "sessions"
        },
        "archived_at": null,
        "revision": 1,
        "title": "Account notification",
        "body": "Welcome! Complete your profile to get started.",
        "read_at": null,
        "created_at": "2026-10-11T10:00:00+00:00"
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 25,
      "total": 1,
      "snapshot_version": 1
    },
    "unread_count": 1
  }
}
```

Query parameters:

| الحقل | القيم / الافتراضي |
| --- | --- |
| `status` | `all` افتراضيًا، أو `read` أو `unread` |
| `category` | اختياري: `security` أو `transactional` أو `marketing` |
| `per_page` | 1–100؛ الافتراضي 25 |
| `page` | يبدأ من 1 |

القائمة تستبعد المؤرشف، والأحدث أولًا. `unread_count` يخص الصندوق كله وليس الفلتر الحالي. `revision` إصدار آخر تعديل للسجل و`meta.snapshot_version` إصدار حالة المستخدم عند القراءة. احفظ نسخة snapshot عند تنفيذ read-all.

`action` قد تكون null أو object مثل `{"screen":"sessions"}`. اربط الشاشات المعروفة بقائمة مسموحة داخل التطبيق؛ إذا ظهر نوع غير معروف اعرض الإشعار بدون تنقل. `read_at` يعني قراءة داخل التطبيق، وليس وصول Push.

### 8.2 العدد الحالي — GET /notifications/unread-count

```sh
curl --fail-with-body -X GET "$CLIENT_BASE/notifications/unread-count" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "unread_count": 1,
    "version": 1
  }
}
```

استخدم العدد المرجعي لشارة التطبيق. وصول version أعلى يخبرك بوجود تغييرات؛ لا تعتبره آخر إصدار تمت مزامنته محليًا قبل تنزيل التغييرات نفسها.

### 8.3 قراءة إشعار — PATCH /notifications/{notification}/read

```sh
curl --fail-with-body -X PATCH "$CLIENT_BASE/notifications/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa/read" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa",
    "category": "security",
    "action": {
      "screen": "sessions"
    },
    "archived_at": null,
    "revision": 2,
    "title": "Account notification",
    "body": "Welcome! Complete your profile to get started.",
    "read_at": "2026-10-11T10:20:00+00:00",
    "created_at": "2026-10-11T10:00:00+00:00"
  }
}
```

بدون Body. كرر الطلب بأمان إذا انقطع الاتصال؛ تاريخ القراءة الأصلي لا يتغير. الاستجابة سجل الإشعار مباشرة داخل data، وليس data.item.

### 8.4 قراءة الجميع — PATCH /notifications/read-all

```sh
curl --fail-with-body -X PATCH "$CLIENT_BASE/notifications/read-all" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"through_version":1}'
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "updated": 1,
    "unread_count": 0,
    "version": 2
  }
}
```

مثال مستقل عن 8.3: كانت هناك رسالة غير مقروءة عند snapshot=1. أرسل `through_version` من آخر snapshot معروض، وليس رقمًا تقديريًا. يتأثر الصندوق كله حتى ذلك الإصدار، وليس الفئة المفلترة فقط. إشعار جديد بعد snapshot يبقى غير مقروء. تكرار الطلب يرجع updated=0 إذا لم يبق شيء مستحق للقراءة؛ version قد يتغير بسبب نشاط آخر.

### 8.5 الأرشفة — PATCH /notifications/{notification}/archive

```sh
curl --fail-with-body -X PATCH "$CLIENT_BASE/notifications/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa/archive" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa",
    "category": "security",
    "action": {
      "screen": "sessions"
    },
    "archived_at": "2026-10-11T10:21:00+00:00",
    "revision": 3,
    "title": "Account notification",
    "body": "Welcome! Complete your profile to get started.",
    "read_at": "2026-10-11T10:20:00+00:00",
    "created_at": "2026-10-11T10:00:00+00:00"
  }
}
```

يختفي الإشعار من الصندوق العادي ومن عدد غير المقروء. لا يوجد endpoint لاستعادة المؤرشف. التكرار يحافظ على تاريخ الأرشفة. سجل الأرشفة يبقى في المزامنة ليزيل الجهاز الآخر النسخة المحلية.

### 8.6 مزامنة تغييرات الأجهزة — GET /notifications?since_version=N

```sh
curl --fail-with-body -X GET "$CLIENT_BASE/notifications?since_version=1&per_page=25" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "items": [
      {
        "id": "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa",
        "category": "security",
        "action": {
          "screen": "sessions"
        },
        "archived_at": "2026-10-11T10:21:00+00:00",
        "revision": 3,
        "title": "Account notification",
        "body": "Welcome! Complete your profile to get started.",
        "read_at": "2026-10-11T10:20:00+00:00",
        "created_at": "2026-10-11T10:00:00+00:00"
      }
    ],
    "meta": {
      "next_cursor": null,
      "snapshot_version": 3
    },
    "unread_count": 0
  }
}
```

في هذا الوضع تُعاد أحدث حالة للسجلات التي `revision > since_version` حتى snapshot المحدد، بما فيها المؤرشف. ليست قائمة بكل الأحداث التاريخية. الترتيب حسب revision ثم ID. فلاتر status/category لا تُطبق في وضع المزامنة. شكل meta مختلف عن قائمة الصندوق.

خوارزمية العميل:

1. للتحميل الأول اجلب الصندوق العادي (بدون since_version)، واحفظ snapshot أول صفحة كنقطة بداية. أكمل الصفحات المطلوبة، وادمج بالـID. السجلات القديمة قبل إضافة الإصدارات قد تحمل revision=0 ولا تظهر في طلب since_version=0؛ لذلك لا تستبدل التحميل الأول بمزامنة فقط.
2. نفذ المزامنة من الإصدار المحفوظ. احفظ snapshot أول استجابة لهذه الجولة.
3. إذا كان next_cursor غير null، استخدمه مع **نفس** since_version و`until_version` مساويًا للـsnapshot المحفوظ. لا تستخدم page في هذه الجولة.
4. ادمج السجل بحسب ID وrevision؛ لا تستبدل نسخة أحدث بأقدم. عند archived_at غير null احذفه من عرض الصندوق مع حفظ ما يلزم لمنع إعادته بنسخة قديمة.
5. بعد اكتمال كل الصفحات فقط، احفظ snapshot كآخر إصدار مزامن. إن فشلت صفحة احتفظ بالإصدار السابق وأعد المحاولة. شغّل جولة لاحقة لالتقاط تغييرات أثناء التحميل.
6. شغّل المزامنة عند فتح التطبيق وعودة الاتصال وحدث Socket، مع منع جولتين متداخلتين لنفس الحساب. إذا فقدت النسخة المحلية امسح الإصدار المحفوظ وأعد التحميل الأول.

مثال طلب الصفحة التالية، بالقيم المأخوذة من الجولة الفعلية:

```sh
export SINCE_VERSION='1'
export SNAPSHOT_VERSION='3'
export NEXT_CURSOR='REPLACE_WITH_RETURNED_NEXT_CURSOR'
curl --fail-with-body --get "$CLIENT_BASE/notifications" \
  -H 'Accept: application/json' -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN" \
  --data-urlencode "since_version=$SINCE_VERSION" \
  --data-urlencode "until_version=$SNAPSHOT_VERSION" \
  --data-urlencode "cursor=$NEXT_CURSOR" \
  --data-urlencode 'per_page=25'
```

الاستجابة بنفس شكل المزامنة السابق؛ next_cursor=null يعني انتهاء الجولة. يمكن أن تكون items فارغة مع snapshot أحدث. لا تزيد العداد محليًا على كل حدث؛ التكرار وتأخر التسليم ممكنان، لذا استعمل العدد المرجعي.

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [NotificationController.php](../../app/Http/Controllers/Shared/NotificationController.php)

## فلاتر العرض والمزامنة

عرض الصندوق يدعم `created_from`, `created_to`, `sort=id|created_at` و`sort_type=asc|desc`، أو `sort=-created_at,id`، مع status/category المعتادة. `since_version` يفعّل وضع المزامنة: الجمع معه بفلاتر العرض أو sort/sort_type/page يرجع 422، وكذلك cursor/until_version دون since_version. [العقد الكامل للفلاتر](../Shared/list-filtering.md).
