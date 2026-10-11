# Vendor — الجلسات والخروج

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. بعد الدخول، اقرأ الجلسات واعرض اسم الجهاز ووقت النشاط.
2. احذف جلسة بعينها لإيقاف وصول ذلك الجهاز واشتراك Push الخاص به.
3. logout يسحب التوكن الحالي؛ logout-all يسحب جميع الجلسات.
4. امسح بيانات الحساب محليًا وافصل Socket.IO وأوقف محاولات إعادة الاتصال.

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/vendor/auth/logout` |
| `GET` | `/api/v1/vendor/auth/sessions` |
| `DELETE` | `/api/v1/vendor/auth/sessions/{session}` |
| `POST` | `/api/v1/vendor/auth/logout-all` |

## `POST /api/v1/vendor/auth/logout`

Revokes the current bearer token. Other sessions remain valid.

Requires a bearer token for the Vendor actor.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/vendor/auth/logout" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": "You have signed out.",
  "data": null
}
```

## `GET /api/v1/vendor/auth/sessions`

Returns device names and activity timestamps, never bearer tokens. Expired sessions are excluded.

Requires a bearer token for the Vendor actor.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

[دليل الفلاتر والترتيب](../Shared/list-filtering.md)

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `sort` | query | لا | `{"type":"string","maxLength":100}` |
| `sort_type` | query | لا | `{"type":"string","enum":["asc","desc"]}` |
| `search` | query | لا | `{"type":"string","minLength":1,"maxLength":100}` |
| `is_current` | query | لا | `{"type":"boolean"}` |
| `created_from` | query | لا | `{"type":"string","format":"date"}` |
| `created_to` | query | لا | `{"type":"string","format":"date"}` |
| `last_used_from` | query | لا | `{"type":"string","format":"date"}` |
| `last_used_to` | query | لا | `{"type":"string","format":"date"}` |

Allowed fields: id, created_at, last_used_at. Up to three comma-separated fields; - means descending. Default: -id. A unique id tie-breaker is appended. Nullable values sort last.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/vendor/auth/sessions" \
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
        "id": 42,
        "device_name": "Ahmed iPhone",
        "is_current": true,
        "created_at": "2026-10-11T10:00:00+00:00",
        "last_used_at": "2026-10-11T10:00:00+00:00",
        "expires_at": "2026-10-12T10:00:00+00:00"
      }
    ]
  }
}
```

## `DELETE /api/v1/vendor/auth/sessions/{session}`

Also removes its push subscription. A session belonging to another account returns 404.

Requires a bearer token for the Vendor actor.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `session` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X DELETE "$API_BASE/vendor/auth/sessions/41" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

## `POST /api/v1/vendor/auth/logout-all`

Revokes all bearer tokens and push subscriptions, including this session.

Requires a bearer token for the Vendor actor.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/vendor/auth/logout-all" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [LogoutController.php](../../app/Http/Controllers/Shared/LogoutController.php)
- [SessionController.php](../../app/Http/Controllers/Shared/SessionController.php)

## البحث والترتيب

`GET /api/v1/vendor/auth/sessions` يقبل `search` لاسم الجهاز و`is_current=1|0`، و`created_from/to` و`last_used_from/to`. رتب بـ `sort=id|created_at|last_used_at` مع `sort_type=asc|desc` أو الصيغة المتعددة `sort=-last_used_at,id`. تبقى `data.items` كاملة للجلسات الصالحة دون pagination. [قواعد الفلترة](../Shared/list-filtering.md).
