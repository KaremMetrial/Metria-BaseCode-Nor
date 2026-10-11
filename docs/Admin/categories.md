# Admin — التصنيفات

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

اقرأ القائمة ثم تفاصيل التصنيف عند اختياره. البحث العام يدعم search أو keyword وparent_id/root_only، وsort بحقول id/sort_order/name وsort_type، وper_page بين 1 و100. تظهر السلسلة النشطة فقط.

الأدمن يقرأ حتى التصنيفات غير النشطة، وينشئ/يعدّل الترجمات والوالد والترتيب. الحد الأقصى لعمق الشجرة 5؛ الدورات تمنع، وحذف تصنيف له أبناء يعيد RESOURCE_IN_USE. صلاحيات الإدارة categories.read/create/update/delete.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/categories` |
| `POST` | `/api/v1/admin/categories` |
| `PATCH` | `/api/v1/admin/categories/{category}` |
| `DELETE` | `/api/v1/admin/categories/{category}` |

## `GET /api/v1/admin/categories`

Includes inactive categories and translations; ordered by sort order and ID.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `categories.read`.

### Path / Query

[قواعد الفلترة والترتيب والأمثلة](../Shared/list-filtering.md)

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `sort` | query | لا | `{"type":"string","maxLength":100}` |
| `sort_type` | query | لا | `{"type":"string","enum":["asc","desc"]}` |
| `page` | query | لا | `{"type":"integer","default":1,"minimum":1}` |
| `per_page` | query | لا | `{"type":"integer","default":25,"minimum":1,"maximum":100}` |
| `search` | query | لا | `{"type":"string","minLength":1,"maxLength":100}` |
| `keyword` | query | لا | `{"type":"string","minLength":1,"maxLength":100}` |
| `parent_id` | query | لا | `{"type":["integer","null"],"minimum":1}` |
| `is_active` | query | لا | `{"type":"boolean"}` |
| `root_only` | query | لا | `{"type":"boolean"}` |

Allowed fields: id, sort_order, name. Up to three comma-separated fields; - means descending. Default: sort_order,id. A unique id tie-breaker is appended. Nullable values sort last.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/categories" \
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
        "id": 1,
        "parent_id": null,
        "name": "Services",
        "description": "Available services",
        "is_active": true,
        "sort_order": 0,
        "translations": {
          "en": {
            "name": "Services",
            "description": "Available services"
          },
          "ar": {
            "name": "الخدمات",
            "description": "الخدمات المتاحة"
          }
        }
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

## `POST /api/v1/admin/categories`

Requires a name in the default locale (en). Optional ar translations are supported. Maximum tree depth is five.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `categories.create`.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `parent_id` | integer/null | اختياري/شرطي |   |
| `is_active` | boolean | اختياري/شرطي |   |
| `sort_order` | integer | اختياري/شرطي | min: 0; max: 65535  |
| `translations` | object | نعم |   |
| `translations.en` | object | نعم |   |
| `translations.en.name` | string | نعم | maxLength: 255  |
| `translations.en.description` | string/null | اختياري/شرطي | maxLength: 255  |
| `translations.ar` | object | اختياري/شرطي |   |
| `translations.ar.name` | string | عند إرسال الأب | maxLength: 255  |
| `translations.ar.description` | string/null | اختياري/شرطي | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/categories" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"parent_id":null,"is_active":true,"sort_order":0,"translations":{"en":{"name":"Services","description":"Available services"},"ar":{"name":"الخدمات","description":"الخدمات المتاحة"}}}'
```

### مثال الاستجابة — HTTP 201

```json
{
  "success": true,
  "message": "Category saved.",
  "data": {
    "id": 1,
    "parent_id": null,
    "name": "Services",
    "description": "Available services",
    "is_active": true,
    "sort_order": 0,
    "translations": {
      "en": {
        "name": "Services",
        "description": "Available services"
      },
      "ar": {
        "name": "الخدمات",
        "description": "الخدمات المتاحة"
      }
    }
  }
}
```

## `PATCH /api/v1/admin/categories/{category}`

Partial update. A supplied locale object must contain its name. Parent changes cannot create cycles or exceed five levels.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `categories.update`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `category` | path | نعم | `{"type": "integer"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `parent_id` | integer/null | اختياري/شرطي |   |
| `is_active` | boolean | اختياري/شرطي |   |
| `sort_order` | integer | اختياري/شرطي | min: 0; max: 65535  |
| `translations` | object | اختياري/شرطي |   |
| `translations.en` | object | اختياري/شرطي |   |
| `translations.en.name` | string | عند إرسال الأب | maxLength: 255  |
| `translations.en.description` | string/null | اختياري/شرطي | maxLength: 255  |
| `translations.ar` | object | اختياري/شرطي |   |
| `translations.ar.name` | string | عند إرسال الأب | maxLength: 255  |
| `translations.ar.description` | string/null | اختياري/شرطي | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X PATCH "$API_BASE/admin/categories/1" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"parent_id":1,"is_active":true,"sort_order":1,"translations":{"en":{"name":"Ahmed Ali","description":null},"ar":{"name":"Ahmed Ali","description":null}}}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": "Category saved.",
  "data": {
    "id": 1,
    "parent_id": null,
    "name": "Services",
    "description": "Available services",
    "is_active": true,
    "sort_order": 0,
    "translations": {
      "en": {
        "name": "Services",
        "description": "Available services"
      },
      "ar": {
        "name": "الخدمات",
        "description": "الخدمات المتاحة"
      }
    }
  }
}
```

## `DELETE /api/v1/admin/categories/{category}`

Deletion fails with RESOURCE_IN_USE when the category has children.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `categories.delete`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `category` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X DELETE "$API_BASE/admin/categories/1" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": "Category deleted.",
  "data": null
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [CategoryController.php](../../app/Http/Controllers/Admin/CategoryController.php)
