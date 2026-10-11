# Admin — إدارة الدول

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اعرض جميع الدول، بما فيها غير النشطة.
2. أنشئ الدولة بترجمة en وiso2 وcalling_code؛ أضف باقي بيانات العرض عند الحاجة.
3. PATCH يغيّر الحقول المرسلة فقط؛ أي locale مرسل يتطلب name.
4. الحذف يمنع عند وجود مراجع مرتبطة؛ يمكن تعطيل السجل بدلًا من حذفه.

القائمة غير مقسمة إلى صفحات. القراءة locations.read والكتابة locations.manage. إلغاء تفعيل الدولة يخفي أبناءها في القراءة العامة.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/locations/countries` |
| `POST` | `/api/v1/admin/locations/countries` |
| `GET` | `/api/v1/admin/locations/countries/{country}` |
| `PATCH` | `/api/v1/admin/locations/countries/{country}` |
| `DELETE` | `/api/v1/admin/locations/countries/{country}` |

## `GET /api/v1/admin/locations/countries`

Includes inactive records and all translations. This list is not paginated.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

الصلاحيات في المسار: `locations.read`.

### Path / Query

[دليل الفلاتر والترتيب](../Shared/list-filtering.md)

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `sort` | query | لا | `{"type":"string","maxLength":100}` |
| `sort_type` | query | لا | `{"type":"string","enum":["asc","desc"]}` |
| `search` | query | لا | `{"type":"string","minLength":1,"maxLength":100}` |
| `iso2` | query | لا | `{"type":"string"}` |
| `calling_code` | query | لا | `{"type":"string","maxLength":8}` |
| `is_active` | query | لا | `{"type":"boolean"}` |

Allowed fields: id, sort_order, name, iso2, iso3, calling_code. Up to three comma-separated fields; - means descending. Default: sort_order,iso2. A unique id tie-breaker is appended. Nullable values sort last.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/locations/countries" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 1,
      "iso2": "EG",
      "iso3": "EGY",
      "calling_code": "+20",
      "currency_code": "EGP",
      "phone_example": "01012345678",
      "numeric_code": "818",
      "timezone_default": "Africa/Cairo",
      "is_active": true,
      "sort_order": 0,
      "translations": {
        "en": {
          "name": "Egypt",
          "nationality": "Egyptian"
        },
        "ar": {
          "name": "مصر",
          "nationality": "مصري"
        }
      },
      "created_at": "2026-10-11T10:00:00+00:00",
      "updated_at": "2026-10-11T10:00:00+00:00"
    }
  ]
}
```

## `POST /api/v1/admin/locations/countries`

Requires an English name in translations.en. Arabic is optional; every supplied locale object must include its name. Only en and ar translation keys are accepted.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

الصلاحيات في المسار: `locations.manage`.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `iso2` | string | نعم | minLength: 2; maxLength: 2; pattern: "^[A-Z]{2}$"  |
| `iso3` | string/null | اختياري/شرطي | minLength: 3; maxLength: 3; pattern: "^[A-Z]{3}$"  |
| `numeric_code` | string/null | اختياري/شرطي | minLength: 3; maxLength: 3; pattern: "^[0-9]{3}$"  |
| `calling_code` | string | نعم | maxLength: 8; pattern: "^\\+[0-9]{1,4}$"  |
| `currency_code` | string/null | اختياري/شرطي | minLength: 3; maxLength: 3; pattern: "^[A-Z]{3}$"  |
| `timezone_default` | string/null | اختياري/شرطي |   |
| `phone_example` | string/null | اختياري/شرطي | maxLength: 32  |
| `is_active` | boolean | اختياري/شرطي |   |
| `sort_order` | integer | اختياري/شرطي | min: 0; max: 65535  |
| `translations` | object | نعم |   |
| `translations.en` | object | نعم |   |
| `translations.en.name` | string | نعم | maxLength: 255  |
| `translations.en.nationality` | string/null | اختياري/شرطي | maxLength: 255  |
| `translations.ar` | object | اختياري/شرطي |   |
| `translations.ar.name` | string | عند إرسال الأب | maxLength: 255  |
| `translations.ar.nationality` | string/null | اختياري/شرطي | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/locations/countries" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"iso2":"EG","iso3":"EGY","calling_code":"+20","currency_code":"EGP","phone_example":"01012345678","numeric_code":"818","timezone_default":"Africa/Cairo","is_active":true,"sort_order":0,"translations":{"en":{"name":"Egypt","nationality":"Egyptian"},"ar":{"name":"مصر","nationality":"مصري"}}}'
```

### مثال الاستجابة — HTTP 201

```json
{
  "success": true,
  "message": "Country created successfully.",
  "data": {
    "id": 1,
    "iso2": "EG",
    "iso3": "EGY",
    "calling_code": "+20",
    "currency_code": "EGP",
    "phone_example": "01012345678",
    "numeric_code": "818",
    "timezone_default": "Africa/Cairo",
    "is_active": true,
    "sort_order": 0,
    "translations": {
      "en": {
        "name": "Egypt",
        "nationality": "Egyptian"
      },
      "ar": {
        "name": "مصر",
        "nationality": "مصري"
      }
    },
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `GET /api/v1/admin/locations/countries/{country}`

Returns administrative fields and all translations, including inactive records.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

الصلاحيات في المسار: `locations.read`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `country` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/locations/countries/1" \
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
    "iso2": "EG",
    "iso3": "EGY",
    "calling_code": "+20",
    "currency_code": "EGP",
    "phone_example": "01012345678",
    "numeric_code": "818",
    "timezone_default": "Africa/Cairo",
    "is_active": true,
    "sort_order": 0,
    "translations": {
      "en": {
        "name": "Egypt",
        "nationality": "Egyptian"
      },
      "ar": {
        "name": "مصر",
        "nationality": "مصري"
      }
    },
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `PATCH /api/v1/admin/locations/countries/{country}`

Partial update: omit unchanged fields. When providing a locale object, include its name. Parent changes must preserve the location hierarchy.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

الصلاحيات في المسار: `locations.manage`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `country` | path | نعم | `{"type": "integer"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `iso2` | string | اختياري/شرطي | minLength: 2; maxLength: 2; pattern: "^[A-Z]{2}$"  |
| `iso3` | string/null | اختياري/شرطي | minLength: 3; maxLength: 3; pattern: "^[A-Z]{3}$"  |
| `numeric_code` | string/null | اختياري/شرطي | minLength: 3; maxLength: 3; pattern: "^[0-9]{3}$"  |
| `calling_code` | string | اختياري/شرطي | maxLength: 8; pattern: "^\\+[0-9]{1,4}$"  |
| `currency_code` | string/null | اختياري/شرطي | minLength: 3; maxLength: 3; pattern: "^[A-Z]{3}$"  |
| `timezone_default` | string/null | اختياري/شرطي |   |
| `phone_example` | string/null | اختياري/شرطي | maxLength: 32  |
| `is_active` | boolean | اختياري/شرطي |   |
| `sort_order` | integer | اختياري/شرطي | min: 0; max: 65535  |
| `translations` | object | اختياري/شرطي |   |
| `translations.en` | object | اختياري/شرطي |   |
| `translations.en.name` | string | عند إرسال الأب | maxLength: 255  |
| `translations.en.nationality` | string/null | اختياري/شرطي | maxLength: 255  |
| `translations.ar` | object | اختياري/شرطي |   |
| `translations.ar.name` | string | عند إرسال الأب | maxLength: 255  |
| `translations.ar.nationality` | string/null | اختياري/شرطي | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X PATCH "$API_BASE/admin/locations/countries/1" \
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
  "message": "Country updated successfully.",
  "data": {
    "id": 1,
    "iso2": "EG",
    "iso3": "EGY",
    "calling_code": "+20",
    "currency_code": "EGP",
    "phone_example": "01012345678",
    "numeric_code": "818",
    "timezone_default": "Africa/Cairo",
    "is_active": true,
    "sort_order": 2,
    "translations": {
      "en": {
        "name": "Egypt",
        "nationality": "Egyptian"
      },
      "ar": {
        "name": "مصر",
        "nationality": "مصري"
      }
    },
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `DELETE /api/v1/admin/locations/countries/{country}`

Returns a null data payload on success. Referenced locations cannot be deleted and return 409 RESOURCE_IN_USE.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

الصلاحيات في المسار: `locations.manage`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `country` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X DELETE "$API_BASE/admin/locations/countries/1" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": "Country deleted successfully.",
  "data": null
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [CountryController.php](../../app/Http/Controllers/Admin/CountryController.php)
