# Admin — إدارة المحافظات

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اختر دولة من countries.
2. اعرض المحافظات باستخدام country_id المطلوب.
3. أنشئ/حدّث المحافظة وترجماتها؛ code فريد داخل الدولة.
4. لا تنقل سجلًا بما يكسر التسلسل الجغرافي، ولا تحذف سجلًا مستخدمًا.

القراءة locations.read والكتابة locations.manage، والقائمة غير مقسمة إلى صفحات.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/locations/governorates` |
| `POST` | `/api/v1/admin/locations/governorates` |
| `GET` | `/api/v1/admin/locations/governorates/{governorate}` |
| `PATCH` | `/api/v1/admin/locations/governorates/{governorate}` |
| `DELETE` | `/api/v1/admin/locations/governorates/{governorate}` |

## `GET /api/v1/admin/locations/governorates`

Requires the country_id query parameter. Includes inactive records and all translations. This list is not paginated.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

الصلاحيات في المسار: `locations.read`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `country_id` | query | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/locations/governorates" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  --get \
  --data-urlencode 'country_id=1'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 1,
      "country_id": 1,
      "code": "C",
      "type": "governorate",
      "is_active": true,
      "sort_order": 0,
      "translations": {
        "en": {
          "name": "Cairo"
        },
        "ar": {
          "name": "القاهرة"
        }
      },
      "created_at": "2026-10-11T10:00:00+00:00",
      "updated_at": "2026-10-11T10:00:00+00:00"
    }
  ]
}
```

## `POST /api/v1/admin/locations/governorates`

Requires an English name in translations.en. Arabic is optional; every supplied locale object must include its name. Only en and ar translation keys are accepted.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

الصلاحيات في المسار: `locations.manage`.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `country_id` | integer | نعم |   |
| `type` | any | اختياري/شرطي |   |
| `code` | string/null | اختياري/شرطي | maxLength: 16  |
| `is_active` | boolean | اختياري/شرطي |   |
| `sort_order` | integer | اختياري/شرطي | min: 0; max: 65535  |
| `translations` | object | نعم |   |
| `translations.en` | object | نعم |   |
| `translations.en.name` | string | نعم | maxLength: 255  |
| `translations.ar` | object | اختياري/شرطي |   |
| `translations.ar.name` | string | عند إرسال الأب | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/locations/governorates" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"country_id":1,"code":"C","type":"governorate","is_active":true,"sort_order":0,"translations":{"en":{"name":"Cairo"},"ar":{"name":"القاهرة"}}}'
```

### مثال الاستجابة — HTTP 201

```json
{
  "success": true,
  "message": "Governorate created successfully.",
  "data": {
    "id": 1,
    "country_id": 1,
    "code": "C",
    "type": "governorate",
    "is_active": true,
    "sort_order": 0,
    "translations": {
      "en": {
        "name": "Cairo"
      },
      "ar": {
        "name": "القاهرة"
      }
    },
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `GET /api/v1/admin/locations/governorates/{governorate}`

Returns administrative fields and all translations, including inactive records.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

الصلاحيات في المسار: `locations.read`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `governorate` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/locations/governorates/1" \
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
    "id": 1,
    "country_id": 1,
    "code": "C",
    "type": "governorate",
    "is_active": true,
    "sort_order": 0,
    "translations": {
      "en": {
        "name": "Cairo"
      },
      "ar": {
        "name": "القاهرة"
      }
    },
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `PATCH /api/v1/admin/locations/governorates/{governorate}`

Partial update: omit unchanged fields. When providing a locale object, include its name. Parent changes must preserve the location hierarchy.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

الصلاحيات في المسار: `locations.manage`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `governorate` | path | نعم | `{"type": "integer"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `country_id` | integer | اختياري/شرطي |   |
| `type` | any | اختياري/شرطي |   |
| `code` | string/null | اختياري/شرطي | maxLength: 16  |
| `is_active` | boolean | اختياري/شرطي |   |
| `sort_order` | integer | اختياري/شرطي | min: 0; max: 65535  |
| `translations` | object | اختياري/شرطي |   |
| `translations.en` | object | اختياري/شرطي |   |
| `translations.en.name` | string | عند إرسال الأب | maxLength: 255  |
| `translations.ar` | object | اختياري/شرطي |   |
| `translations.ar.name` | string | عند إرسال الأب | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X PATCH "$API_BASE/admin/locations/governorates/1" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"sort_order":2}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": "Governorate updated successfully.",
  "data": {
    "id": 1,
    "country_id": 1,
    "code": "C",
    "type": "governorate",
    "is_active": true,
    "sort_order": 2,
    "translations": {
      "en": {
        "name": "Cairo"
      },
      "ar": {
        "name": "القاهرة"
      }
    },
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `DELETE /api/v1/admin/locations/governorates/{governorate}`

Returns a null data payload on success. Referenced locations cannot be deleted and return 409 RESOURCE_IN_USE.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

الصلاحيات في المسار: `locations.manage`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `governorate` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X DELETE "$API_BASE/admin/locations/governorates/1" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": "Governorate deleted successfully.",
  "data": null
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [GovernorateController.php](../../app/Http/Controllers/Admin/GovernorateController.php)
