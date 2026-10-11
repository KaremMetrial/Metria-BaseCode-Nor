# Vendor — صندوق الإشعارات والمزامنة

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اعرض القائمة أو unread-count واحفظ snapshot_version.
2. قراءة إشعار وأرشفته عمليتان قابلتان للتكرار.
3. لقراءة الجميع أرسل through_version من القائمة؛ الإشعارات الأحدث تبقى غير مقروءة.
4. للمزامنة أرسل since_version، ثم ثبّت until_version على snapshot_version الراجع، وواصل cursor حتى يصبح next_cursor=null.
5. ارفع مؤشر المزامنة المحلي فقط بعد استهلاك جميع الصفحات، وطبّق archived_at كعلامة حذف من الصندوق.

ادمج بالـid وrevision. في وضع المزامنة لا تُطبّق فلاتر الصندوق status/category. الإشعارات القديمة قد تحتفظ بلغتها وقت الإنشاء. لا يوجد endpoint لإرسال إشعار عشوائي من التطبيق.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/vendor/notifications/unread-count` |
| `PATCH` | `/api/v1/vendor/notifications/read-all` |
| `PATCH` | `/api/v1/vendor/notifications/{notification}/archive` |
| `GET` | `/api/v1/vendor/notifications` |
| `PATCH` | `/api/v1/vendor/notifications/{notification}/read` |

## `GET /api/v1/vendor/notifications/unread-count`

Returns the current authoritative unread count and synchronization version across all devices.

Requires a bearer token for the Vendor actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/vendor/notifications/unread-count" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN"
```

### مثال الاستجابة — HTTP 200

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

## `PATCH /api/v1/vendor/notifications/read-all`

Send through_version from the list response. Notifications created after that snapshot remain unread, even if this request is retried.

Requires a bearer token for the Vendor actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `through_version` | integer | نعم | min: 0  |

### مثال الطلب

```sh
curl --fail-with-body -X PATCH "$API_BASE/vendor/notifications/read-all" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"through_version":1}'
```

### مثال الاستجابة — HTTP 200

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

## `PATCH /api/v1/vendor/notifications/{notification}/archive`

Removes the notification from the inbox and unread count. Synchronization responses retain an archived tombstone for other devices.

Requires a bearer token for the Vendor actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `notification` | path | نعم | `{"type": "string", "format": "uuid"}` |

### مثال الطلب

```sh
curl --fail-with-body -X PATCH "$API_BASE/vendor/notifications/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa/archive" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN"
```

### مثال الاستجابة — HTTP 200

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
    "archived_at": "2026-10-11T10:00:00+00:00",
    "revision": 2,
    "title": "Account notification",
    "body": "A new sign-in to your account was recorded.",
    "read_at": null,
    "created_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `GET /api/v1/vendor/notifications`

Filters by read state and category. Pass since_version to fetch changes including archived tombstones; pass its snapshot_version as until_version and next_cursor on subsequent pages; advance since_version only after all pages are consumed. Unread count excludes archived notifications.

Requires a bearer token for the Vendor actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `status` | query | لا | `{"type": "string", "enum": ["read", "unread", "all"]}` |
| `category` | query | لا | `{"type": "string", "enum": ["security", "transactional", "marketing"]}` |
| `per_page` | query | لا | `{"type": "integer", "minimum": 1, "maximum": 100}` |
| `page` | query | لا | `{"type": "integer", "minimum": 1}` |
| `since_version` | query | لا | `{"type": "integer", "minimum": 0}` |
| `until_version` | query | لا | `{"type": "integer", "minimum": 0}` |
| `cursor` | query | لا | `{"type": "string", "maxLength": 2048}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/vendor/notifications" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN"
```

### مثال الاستجابة — HTTP 200

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
        "body": "A new sign-in to your account was recorded.",
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

## `PATCH /api/v1/vendor/notifications/{notification}/read`

Repeated requests preserve the original read timestamp. Another account's notification returns 404.

Requires a bearer token for the Vendor actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `notification` | path | نعم | `{"type": "string", "format": "uuid"}` |

### مثال الطلب

```sh
curl --fail-with-body -X PATCH "$API_BASE/vendor/notifications/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa/read" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN"
```

### مثال الاستجابة — HTTP 200

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
    "body": "A new sign-in to your account was recorded.",
    "read_at": "2026-10-11T10:00:00+00:00",
    "created_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [NotificationController.php](../../app/Http/Controllers/Shared/NotificationController.php)
