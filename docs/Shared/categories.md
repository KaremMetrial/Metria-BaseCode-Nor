# Shared — التصنيفات

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

اقرأ القائمة ثم تفاصيل التصنيف عند اختياره. البحث العام يدعم keyword وparent_id وsort=sort_order|id وper_page بين 1 و 100. تظهر السلسلة النشطة فقط.

الأدمن يقرأ حتى التصنيفات غير النشطة، وينشئ/يعدّل الترجمات والوالد والترتيب. الحد الأقصى لعمق الشجرة 5؛ الدورات تمنع، وحذف تصنيف له أبناء يعيد RESOURCE_IN_USE. صلاحيات الإدارة categories.read/create/update/delete.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/categories` |
| `GET` | `/api/v1/categories/{category}` |

## `GET /api/v1/categories`

Returns active categories whose entire ancestor chain is active. Supports keyword, parent, sorting and pagination filters.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `keyword` | query | لا | `{"type": "string", "maxLength": 100}` |
| `parent_id` | query | لا | `{"type": ["integer", "null"], "minimum": 1}` |
| `sort` | query | لا | `{"type": "string", "enum": ["sort_order", "id"]}` |
| `page` | query | لا | `{"type": "integer", "minimum": 1}` |
| `per_page` | query | لا | `{"type": "integer", "minimum": 1, "maximum": 100}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/categories" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en'
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
        "sort_order": 0
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

## `GET /api/v1/categories/{category}`

Returns one visible category in the requested language. Hidden categories and categories below hidden ancestors return 404.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `category` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/categories/1" \
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
    "parent_id": null,
    "name": "Services",
    "description": "Available services",
    "is_active": true,
    "sort_order": 0
  }
}
```

## التعامل مع الفشل

المسارات عامة ولا تحتاج تسجيل دخول. 404: المورد غير موجود أوغير منشور/نشط وفق شروط الميزة. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [CategoryController.php](../../app/Http/Controllers/Shared/CategoryController.php)
