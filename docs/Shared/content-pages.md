# Shared — الصفحات العامة والشروط

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اقرأ قائمة الصفحات المنشورة وحدد عناصر التنقل من navigation.
2. افتح الصفحة بالـslug وX-Locale.
3. اعرض content_html المنظّف أو Markdown بمحرك آمن في التطبيق.
4. للتسجيل استخدم terms_version وterms_revision_id من client/auth/requirements.

المسودات والمؤرشفة تعيد 404. صفحات البداية terms/privacy-policy/about-us/refund-policy/contact-us تُزرع مسودات فارغة حتى يكتب الأدمن المحتوى وينشره. توجد نسخة متصفح GET /pages/{slug}?locale=ar. [إدارة الصفحات](../Admin/pages.md).

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/pages` |
| `GET` | `/api/v1/pages/{slug}` |

## `GET /api/v1/pages`

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/pages" \
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
      "slug": "about-us",
      "title": "About us",
      "content": "Welcome to Metrial.",
      "content_html": "<p>Welcome to Metrial.</p>\n",
      "media": [],
      "version": 1,
      "revision_id": 12,
      "terms_version": null,
      "navigation": {
        "sort_order": 0,
        "show_in_footer": true,
        "show_in_settings": true
      },
      "published_at": "2026-10-11T10:00:00+00:00",
      "url": "http://localhost/pages/about-us?locale=en"
    }
  ]
}
```

## `GET /api/v1/pages/{slug}`

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `slug` | path | نعم | `{"type": "string"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/pages/about-us" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "slug": "about-us",
    "title": "About us",
    "content": "Welcome to Metrial.",
    "content_html": "<p>Welcome to Metrial.</p>\n",
    "media": [],
    "version": 1,
    "revision_id": 12,
    "terms_version": null,
    "navigation": {
      "sort_order": 0,
      "show_in_footer": true,
      "show_in_settings": true
    },
    "published_at": "2026-10-11T10:00:00+00:00",
    "url": "http://localhost/pages/about-us?locale=en"
  }
}
```

## التعامل مع الفشل

المسارات عامة ولا تحتاج تسجيل دخول. 404: المورد غير موجود أوغير منشور/نشط وفق شروط الميزة. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [PageController.php](../../app/Http/Controllers/Shared/PageController.php)

## عرض الصفحة في المتصفح

```sh
curl --fail-with-body 'http://localhost/pages/about-us?locale=ar' -H 'Accept: text/html'
```

الرد HTTP200 وContent-Type text/html، يحتوي عنوان النسخة المنشورة ومحتواها ونظام التنقل؛ لا يوجد غلاف success/data. المسودة والمؤرشفة 404. مسار الويب يختار اللغة من locale أوAccept-Language، ولا يتبع جميع أولويات locale الخاصة بـAPI. صفحة contact-us تتضمن نموذجًا عند نشرها؛ [تفاصيل النموذج](contact-us.md).
