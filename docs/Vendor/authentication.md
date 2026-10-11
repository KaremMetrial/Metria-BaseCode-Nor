# Vendor — الدخول والتسجيل

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
| `POST` | `/api/v1/vendor/auth/otp/request` |
| `POST` | `/api/v1/vendor/auth/otp/verify` |

## `POST /api/v1/vendor/auth/otp/request`

Send the selected country ID and a mobile phone number. Returns a challenge ID; the code is delivered by SMS and is never included in this response.

OTP request rate limit: 3 requests/minute per IP.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `country_id` | integer | نعم |   |
| `phone` | string | نعم | maxLength: 40  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/vendor/auth/otp/request" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H 'Content-Type: application/json' \
  --data '{"country_id":1,"phone":"+201012345678"}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": "A verification code has been sent.",
  "data": {
    "challenge_id": "11111111-1111-4111-8111-111111111111",
    "expires_in": 300,
    "resend_after": 60
  }
}
```

## `POST /api/v1/vendor/auth/otp/verify`

Submit the same country and phone with the challenge ID and six-digit code. Creates an account when needed and returns its profile and bearer token. For clients, next_step identifies whether profile completion is required; expires_at identifies token expiry. Optional device_id rotates an existing session for that installation. New vendors await administrator approval.

OTP verification rate limit: 5 requests/minute per IP.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `country_id` | integer | نعم |   |
| `phone` | string | نعم | maxLength: 40  |
| `device_name` | string | اختياري/شرطي | maxLength: 100  |
| `device_id` | string | اختياري/شرطي | الصيغة: "uuid"  |
| `challenge_id` | string | نعم | الصيغة: "uuid"  |
| `code` | string | نعم | pattern: "^[0-9]{6}$"  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/vendor/auth/otp/verify" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H 'Content-Type: application/json' \
  --data '{"country_id":1,"phone":"+201012345678","challenge_id":"11111111-1111-4111-8111-111111111111","code":"123456","device_name":"Ahmed iPhone","device_id":"22222222-2222-4222-8222-222222222222"}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": "You have signed in successfully.",
  "data": {
    "user": {
      "id": 57,
      "name": "",
      "phone": "+201012345678",
      "phone_country_id": 1,
      "phone_verified_at": "2026-10-11T10:00:00+00:00",
      "type": "vendor",
      "status": "pending",
      "locale": "en",
      "timezone": null,
      "has_avatar": false,
      "avatar_url": null,
      "email": null
    },
    "token": "42|REPLACE_WITH_RETURNED_TOKEN",
    "expires_at": "2026-10-12T10:00:00+00:00",
    "next_step": "home"
  }
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [OtpController.php](../../app/Http/Controllers/Shared/OtpController.php)
