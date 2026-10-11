# Shared — الدول والمحافظات والمدن العامة

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اقرأ countries واختر id من الاستجابة.
2. اقرأ governorates الخاصة بالدولة.
3. اقرأ cities الخاصة بالمحافظة.
4. استخدم city/governorate/country show عند استعادة الاختيارات.

تظهر السجلات النشطة فقط مع سلسلة أسلاف نشطة؛ القوائم غير مقسمة إلى صفحات. IDs في الأمثلة توضيحية ولا تُثبّت داخل التطبيق. country_id للهاتف هو معرّف هذه الدولة، وليس calling_code.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/locations/countries` |
| `GET` | `/api/v1/locations/countries/{country}` |
| `GET` | `/api/v1/locations/countries/{country}/governorates` |
| `GET` | `/api/v1/locations/governorates/{governorate}` |
| `GET` | `/api/v1/locations/governorates/{governorate}/cities` |
| `GET` | `/api/v1/locations/cities/{city}` |

## `GET /api/v1/locations/countries`

Returns all active countries, ordered for display. Use the ID and calling code for phone authentication.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/locations/countries" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en'
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
      "name": "Egypt",
      "nationality": "Egyptian",
      "calling_code": "+20",
      "currency_code": "EGP",
      "phone_example": "01012345678"
    }
  ]
}
```

## `GET /api/v1/locations/countries/{country}`

Returns an active country in the requested language.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `country` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/locations/countries/1" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en'
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
    "name": "Egypt",
    "nationality": "Egyptian",
    "calling_code": "+20",
    "currency_code": "EGP",
    "phone_example": "01012345678"
  }
}
```

## `GET /api/v1/locations/countries/{country}/governorates`

Returns active governorates of an active country. The list is not paginated.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `country` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/locations/countries/1/governorates" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en'
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
      "name": "Cairo",
      "type": "governorate"
    }
  ]
}
```

## `GET /api/v1/locations/governorates/{governorate}`

The governorate and its parent country must be active.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `governorate` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/locations/governorates/1" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 1,
    "country_id": 1,
    "name": "Cairo",
    "type": "governorate"
  }
}
```

## `GET /api/v1/locations/governorates/{governorate}/cities`

Returns active cities only when their governorate and country are active. The list is not paginated.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `governorate` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/locations/governorates/1/cities" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en'
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
      "name": "Nasr City",
      "latitude": 30.0566,
      "longitude": 31.3301
    }
  ]
}
```

## `GET /api/v1/locations/cities/{city}`

The city and all its ancestors must be active. Includes coordinates when configured.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `city` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/locations/cities/1" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 1,
    "governorate_id": 1,
    "name": "Nasr City",
    "latitude": 30.0566,
    "longitude": 31.3301
  }
}
```

## التعامل مع الفشل

المسارات عامة ولا تحتاج تسجيل دخول. 404: المورد غير موجود أوغير منشور/نشط وفق شروط الميزة. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [CityController.php](../../app/Http/Controllers/Shared/CityController.php)
- [CountryController.php](../../app/Http/Controllers/Shared/CountryController.php)
- [GovernorateController.php](../../app/Http/Controllers/Shared/GovernorateController.php)
