# Admin — إدارة رسائل تواصل معنا

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اقرأ قائمة الرسائل مع status أو search عند الحاجة.
2. افتح الرسالة بالـid وراجع الهاتف والموضوع.
3. انتقل من new إلى reviewed ثم closed.

هذه ليست محادثة ولا تملك reply endpoint؛ دعم المحادثات منفصل. يلزم contact.read للقراءة وcontact.manage لتغيير الحالة. لا تُعرض هذه السجلات لأي زائر أو عميل آخر.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/contact-messages` |
| `GET` | `/api/v1/admin/contact-messages/{message}` |
| `PATCH` | `/api/v1/admin/contact-messages/{message}/status` |

## `GET /api/v1/admin/contact-messages`

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `contact.read`.

### Path / Query

[قواعد الفلترة والترتيب والأمثلة](../Shared/list-filtering.md)

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `sort` | query | لا | `{"type":"string","maxLength":100}` |
| `sort_type` | query | لا | `{"type":"string","enum":["asc","desc"]}` |
| `page` | query | لا | `{"type":"integer","default":1,"minimum":1}` |
| `per_page` | query | لا | `{"type":"integer","default":25,"minimum":1,"maximum":100}` |
| `search` | query | لا | `{"type":"string","minLength":1,"maxLength":100}` |
| `status` | query | لا | `{"anyOf":[{"type":"string","enum":["new","reviewed","closed"]},{"type":"array","items":{"type":"string","enum":["new","reviewed","closed"]},"minItems":1,"maxItems":20}]}` |
| `created_from` | query | لا | `{"type":"string","format":"date"}` |
| `created_to` | query | لا | `{"type":"string","format":"date"}` |

Allowed fields: id, created_at, updated_at. Up to three comma-separated fields; - means descending. Default: -id. A unique id tie-breaker is appended. Nullable values sort last.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/contact-messages" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "items": [
      {
        "id": 9,
        "reference": "11111111-1111-4111-8111-111111111111",
        "user_id": null,
        "name": "Ahmed Ali",
        "phone": "+201012345678",
        "subject": "Service availability",
        "message": "Is the service available in Cairo?",
        "status": "new",
        "created_at": "2026-10-11T10:00:00+00:00",
        "updated_at": "2026-10-11T10:00:00+00:00"
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 25,
      "total": 1
    }
  }
}
```

## `GET /api/v1/admin/contact-messages/{message}`

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `contact.read`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `message` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/contact-messages/9" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 9,
    "reference": "11111111-1111-4111-8111-111111111111",
    "user_id": null,
    "name": "Ahmed Ali",
    "phone": "+201012345678",
    "subject": "Service availability",
    "message": "Is the service available in Cairo?",
    "status": "new",
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `PATCH /api/v1/admin/contact-messages/{message}/status`

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `contact.read`.

Required permission: `contact.manage`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `message` | path | نعم | `{"type": "integer"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `status` | string | نعم | القيم: ["new", "reviewed", "closed"]  |

### مثال الطلب

```sh
curl --fail-with-body -X PATCH "$API_BASE/admin/contact-messages/9/status" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"status":"reviewed"}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 9,
    "reference": "11111111-1111-4111-8111-111111111111",
    "user_id": null,
    "name": "Ahmed Ali",
    "phone": "+201012345678",
    "subject": "Service availability",
    "message": "Is the service available in Cairo?",
    "status": "reviewed",
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [ContactMessageController.php](../../app/Http/Controllers/Admin/ContactMessageController.php)
