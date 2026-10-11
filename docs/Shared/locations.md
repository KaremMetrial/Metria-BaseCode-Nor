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

### Path / Query

[دليل الفلاتر والترتيب](../Shared/list-filtering.md)

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `sort` | query | لا | `{"type":"string","maxLength":100}` |
| `sort_type` | query | لا | `{"type":"string","enum":["asc","desc"]}` |
| `search` | query | لا | `{"type":"string","minLength":1,"maxLength":100}` |
| `iso2` | query | لا | `{"type":"string"}` |
| `calling_code` | query | لا | `{"type":"string","maxLength":8}` |

Allowed fields: id, sort_order, name, iso2, iso3, calling_code. Up to three comma-separated fields; - means descending. Default: sort_order,iso2. A unique id tie-breaker is appended. Nullable values sort last.

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
      "phone_example": "01012345678",
      "phone_format": "national_significant",
      "phone_lengths": [10],
      "phone_regex": "^(?:1[0-25]\\d{8})$"
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
    "phone_example": "01012345678",
    "phone_format": "national_significant",
    "phone_lengths": [10],
    "phone_regex": "^(?:1[0-25]\\d{8})$"
  }
}
```

## `GET /api/v1/locations/countries/{country}/governorates`

Returns active governorates of an active country. The list is not paginated.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

[قواعد الفلترة والترتيب والأمثلة](../Shared/list-filtering.md)

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `country` | path | نعم | `{"type":"integer"}` |
| `sort` | query | لا | `{"type":"string","maxLength":100}` |
| `sort_type` | query | لا | `{"type":"string","enum":["asc","desc"]}` |
| `search` | query | لا | `{"type":"string","minLength":1,"maxLength":100}` |
| `code` | query | لا | `{"type":"string","maxLength":32}` |
| `type` | query | لا | `{"type":"string","enum":["governorate","state","province","emirate","region"]}` |

Allowed fields: id, sort_order, name, code. Up to three comma-separated fields; - means descending. Default: sort_order,id. A unique id tie-breaker is appended. Nullable values sort last.

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

[قواعد الفلترة والترتيب والأمثلة](../Shared/list-filtering.md)

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `governorate` | path | نعم | `{"type":"integer"}` |
| `sort` | query | لا | `{"type":"string","maxLength":100}` |
| `sort_type` | query | لا | `{"type":"string","enum":["asc","desc"]}` |
| `search` | query | لا | `{"type":"string","minLength":1,"maxLength":100}` |
| `code` | query | لا | `{"type":"string","maxLength":32}` |
| `postal_code` | query | لا | `{"type":"string","maxLength":32}` |

Allowed fields: id, sort_order, name, code. Up to three comma-separated fields; - means descending. Default: sort_order,id. A unique id tie-breaker is appended. Nullable values sort last.

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

## قواعد إدخال الهاتف

قائمة الدول وتفاصيل الدولة تعرضان حقولًا محسوبة من libphonenumber المثبتة، وليست أعمدة تُدخل يدويًا أوتُحفظ من الأدمن:

| الحقل | المعنى |
|---|---|
| `phone_format` | `national_significant`: أرقام NSN دون كود الدولة أوبادئة الاتصال المحلية، ودون مسافات/شرطات |
| `phone_lengths` | الأطوال الممكنة كقائمة؛ مصر `[10]`، وقد تقبل دولة أخرى أكثر من طول |
| `phone_regex` | regex كامل مثبت البداية والنهاية، دون delimiters مثل `/.../`؛ يمكن تمريره إلى `new RegExp(...)` |

مع `OTP_MOBILE_ONLY=true` تأتي القواعد من خطة الموبايل، ومع false تأتي من الخطة العامة. عند عدم وجود metadata أوخطة موبايل يرجع `phone_lengths: []` و`phone_regex: null`؛ لا تفسر ذلك كقاعدة تسمح بكل الأرقام. البيانات الأساسية للدولة تبقى ظاهرة للاستخدام الجغرافي.

مثال مصر: الإدخال الذي تُطبق عليه هذه القواعد هو `1012345678`، وصيغته الدولية `+201012345678`. الرقم المحلي `01012345678` ما زال مقبولًا في OTP ويطبّعه السيرفر، لكنه ليس صيغة NSN التي تصفها هذه الحقول. `phone_example` حقل العرض الموجود سابقًا، وقد يكون دوليًا أو محليًا؛ لا تفترض أنه NSN ولا تحذف كل صفر بادئ عالميًا، لأن بعض خطط الترقيم تعتبره جزءًا من الرقم.

```js
function matchesPhoneHint(country, nationalDigits) {
  return /^[0-9]+$/.test(nationalDigits)
    && country.phone_lengths.includes(nationalDigits.length)
    && typeof country.phone_regex === 'string'
    && new RegExp(country.phone_regex).test(nationalDigits);
}

// عندما يكون حقل الإدخال NSN، وكود الدولة مختارًا بشكل مستقل:
const phone = country.calling_code + nationalDigits;
// أرسل { country_id: country.id, phone } إلى OTP.
```

هذه قواعد مساعدة للواجهة؛ السيرفر يظل المرجع للتحقق من الدولة ونوع الرقم، خصوصًا الدول التي تشترك في calling_code. مطابقة regex لا تثبت وجود الرقم أوملكيته؛ OTP هو إثبات الوصول.
