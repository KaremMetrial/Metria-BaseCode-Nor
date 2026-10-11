# Admin — إدارة المدن

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اختر المحافظة.
2. اعرض cities مع governorate_id المطلوب.
3. أنشئ المدينة بترجمة en وأضف الإحداثيات/الرمز البريدي اختياريًا.
4. حدّث الحقول المطلوبة فقط؛ حذف السجل المستخدم يعيد RESOURCE_IN_USE.

القراءة locations.read والكتابة locations.manage، والقائمة غير مقسمة إلى صفحات.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/locations/cities` |
| `POST` | `/api/v1/admin/locations/cities` |
| `GET` | `/api/v1/admin/locations/cities/{city}` |
| `PATCH` | `/api/v1/admin/locations/cities/{city}` |
| `DELETE` | `/api/v1/admin/locations/cities/{city}` |

## `GET /api/v1/admin/locations/cities`

Requires the governorate_id query parameter. Includes inactive records and all translations. This list is not paginated.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

الصلاحيات في المسار: `locations.read`.

### Path / Query

[قواعد الفلترة والترتيب والأمثلة](../Shared/list-filtering.md)

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `sort` | query | لا | `{"type":"string","maxLength":100}` |
| `sort_type` | query | لا | `{"type":"string","enum":["asc","desc"]}` |
| `search` | query | لا | `{"type":"string","minLength":1,"maxLength":100}` |
| `code` | query | لا | `{"type":"string","maxLength":32}` |
| `postal_code` | query | لا | `{"type":"string","maxLength":32}` |
| `is_active` | query | لا | `{"type":"boolean"}` |
| `governorate_id` | query | نعم | `{"type":"integer"}` |

Allowed fields: id, sort_order, name, code. Up to three comma-separated fields; - means descending. Default: sort_order,id. A unique id tie-breaker is appended. Nullable values sort last.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/locations/cities" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  --get \
  --data-urlencode 'governorate_id=1'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 1,
      "governorate_id": 1,
      "latitude": 30.0566,
      "longitude": 31.3301,
      "code": "NC",
      "postal_code": "11765",
      "is_active": true,
      "sort_order": 0,
      "translations": {
        "en": {
          "name": "Nasr City"
        },
        "ar": {
          "name": "مدينة نصر"
        }
      },
      "created_at": "2026-10-11T10:00:00+00:00",
      "updated_at": "2026-10-11T10:00:00+00:00"
    }
  ]
}
```

## `POST /api/v1/admin/locations/cities`

Requires an English name in translations.en. Arabic is optional; every supplied locale object must include its name. Only en and ar translation keys are accepted.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

الصلاحيات في المسار: `locations.manage`.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `governorate_id` | integer | نعم |   |
| `code` | string/null | اختياري/شرطي | maxLength: 16  |
| `postal_code` | string/null | اختياري/شرطي | maxLength: 16  |
| `latitude` | number/null | اختياري/شرطي | min: -90; max: 90  |
| `longitude` | number/null | اختياري/شرطي | min: -180; max: 180  |
| `is_active` | boolean | اختياري/شرطي |   |
| `sort_order` | integer | اختياري/شرطي | min: 0; max: 65535  |
| `translations` | object | نعم |   |
| `translations.en` | object | نعم |   |
| `translations.en.name` | string | نعم | maxLength: 255  |
| `translations.ar` | object | اختياري/شرطي |   |
| `translations.ar.name` | string | عند إرسال الأب | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/locations/cities" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"governorate_id":1,"latitude":30.0566,"longitude":31.3301,"code":"NC","postal_code":"11765","is_active":true,"sort_order":0,"translations":{"en":{"name":"Nasr City"},"ar":{"name":"مدينة نصر"}}}'
```

### مثال الاستجابة — HTTP 201

```json
{
  "success": true,
  "message": "City created successfully.",
  "data": {
    "id": 1,
    "governorate_id": 1,
    "latitude": 30.0566,
    "longitude": 31.3301,
    "code": "NC",
    "postal_code": "11765",
    "is_active": true,
    "sort_order": 0,
    "translations": {
      "en": {
        "name": "Nasr City"
      },
      "ar": {
        "name": "مدينة نصر"
      }
    },
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `GET /api/v1/admin/locations/cities/{city}`

Returns administrative fields and all translations, including inactive records.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

الصلاحيات في المسار: `locations.read`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `city` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/locations/cities/1" \
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
    "governorate_id": 1,
    "latitude": 30.0566,
    "longitude": 31.3301,
    "code": "NC",
    "postal_code": "11765",
    "is_active": true,
    "sort_order": 0,
    "translations": {
      "en": {
        "name": "Nasr City"
      },
      "ar": {
        "name": "مدينة نصر"
      }
    },
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `PATCH /api/v1/admin/locations/cities/{city}`

Partial update: omit unchanged fields. When providing a locale object, include its name. Parent changes must preserve the location hierarchy.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

الصلاحيات في المسار: `locations.manage`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `city` | path | نعم | `{"type": "integer"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `governorate_id` | integer | اختياري/شرطي |   |
| `code` | string/null | اختياري/شرطي | maxLength: 16  |
| `postal_code` | string/null | اختياري/شرطي | maxLength: 16  |
| `latitude` | number/null | اختياري/شرطي | min: -90; max: 90  |
| `longitude` | number/null | اختياري/شرطي | min: -180; max: 180  |
| `is_active` | boolean | اختياري/شرطي |   |
| `sort_order` | integer | اختياري/شرطي | min: 0; max: 65535  |
| `translations` | object | اختياري/شرطي |   |
| `translations.en` | object | اختياري/شرطي |   |
| `translations.en.name` | string | عند إرسال الأب | maxLength: 255  |
| `translations.ar` | object | اختياري/شرطي |   |
| `translations.ar.name` | string | عند إرسال الأب | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X PATCH "$API_BASE/admin/locations/cities/1" \
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
  "message": "City updated successfully.",
  "data": {
    "id": 1,
    "governorate_id": 1,
    "latitude": 30.0566,
    "longitude": 31.3301,
    "code": "NC",
    "postal_code": "11765",
    "is_active": true,
    "sort_order": 2,
    "translations": {
      "en": {
        "name": "Nasr City"
      },
      "ar": {
        "name": "مدينة نصر"
      }
    },
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `DELETE /api/v1/admin/locations/cities/{city}`

Returns a null data payload on success. Referenced locations cannot be deleted and return 409 RESOURCE_IN_USE.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

الصلاحيات في المسار: `locations.manage`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `city` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X DELETE "$API_BASE/admin/locations/cities/1" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": "City deleted successfully.",
  "data": null
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [CityController.php](../../app/Http/Controllers/Admin/CityController.php)
