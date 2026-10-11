# Admin — الدخول والتسجيل

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. جهّز هوية الحساب وبيئة SMS/الدخول.
2. نفّذ طلب الدخول المناسب لنوع الحساب واحفظ التوكن الراجع كاملًا.
3. اقرأ الملف والحالة قبل فتح الميزات المحمية.
4. عند 401 أوقف الطلبات المحمية وارجع للدخول؛ لا يوجد refresh-token endpoint.

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/admin/auth/login` |

## `POST /api/v1/admin/auth/login`

Returns a bearer token and the administrator profile. Invalid credentials or a disabled account return 401.

Additional rate limit: 5 requests/minute per IP.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `email` | string | نعم | الصيغة: "email"; maxLength: 255  |
| `password` | string | نعم | الصيغة: "password"; maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/auth/login" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H 'Content-Type: application/json' \
  --data '{"email":"admin@example.test","password":"REPLACE_WITH_YOUR_PASSWORD"}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": "You have signed in successfully.",
  "data": {
    "user": {
      "id": 1,
      "name": "Admin User",
      "phone": "+201012345678",
      "phone_country_id": 1,
      "phone_verified_at": "2026-10-11T10:00:00+00:00",
      "type": "admin",
      "status": "active",
      "locale": "en",
      "timezone": "Africa/Cairo",
      "has_avatar": false,
      "avatar_url": null,
      "email": "admin@example.test"
    },
    "token": "42|REPLACE_WITH_RETURNED_TOKEN"
  }
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [AdminLoginController.php](../../app/Http/Controllers/Admin/AdminLoginController.php)
