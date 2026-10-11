# Admin — التحقق الحديث وتغيير الهاتف

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. Client فقط: إن مرّت خمس دقائق على تحقق الهاتف، أعد توثيق الجلسة برمز للهاتف الحالي.
2. اطلب رمزًا للرقم الجديد باستخدام country_id وphone.
3. تحقق بالـchallenge والرقم والدولة نفسهما.
4. نجاح التغيير يسحب جميع REST tokens واشتراكات Push؛ أعد دخول الأدمن بالبريد وكلمة المرور.

Admin وVendor يملكان مساري تغيير الهاتف، لكن لا يملكان مسارات client/auth/reauthenticate. فقدان الرقم لدى Client يذهب للدعم؛ لا توجد استعادة بالبريد.

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/admin/profile/phone/request` |
| `POST` | `/api/v1/admin/profile/phone/verify` |

## `POST /api/v1/admin/profile/phone/request`

Clients must have verified their current phone within the last five minutes, through login or reauthentication. Sends a verification code to the proposed new mobile number. The number must not already belong to another account.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

OTP request rate limit: 3 requests/minute per IP.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `country_id` | integer | نعم |   |
| `phone` | string | نعم | maxLength: 40  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/profile/phone/request" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"country_id":1,"phone":"+201112345678"}'
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

## `POST /api/v1/admin/profile/phone/verify`

On success, changes the phone number and revokes ALL bearer tokens, including the current token. For Admin, sign in again using email/password at admin/auth/login; phone OTP login belongs to Client/Vendor.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

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
curl --fail-with-body -X POST "$API_BASE/admin/profile/phone/verify" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"country_id":1,"phone":"+201112345678","challenge_id":"11111111-1111-4111-8111-111111111111","code":"123456","device_name":"Ahmed iPhone","device_id":"22222222-2222-4222-8222-222222222222"}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": "Your phone number has been changed. Please sign in again.",
  "data": {
    "id": 1,
    "name": "Admin User",
    "phone": "+201112345678",
    "phone_country_id": 1,
    "phone_verified_at": "2026-10-11T10:00:00+00:00",
    "type": "admin",
    "status": "active",
    "locale": "en",
    "timezone": "Africa/Cairo",
    "has_avatar": false,
    "avatar_url": null,
    "email": "admin@example.test"
  }
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [PhoneChangeController.php](../../app/Http/Controllers/Shared/PhoneChangeController.php)
