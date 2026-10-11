# Client — التحقق الحديث وتغيير الهاتف

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. Client فقط: إن مرّت خمس دقائق على تحقق الهاتف، أعد توثيق الجلسة برمز للهاتف الحالي.
2. اطلب رمزًا للرقم الجديد باستخدام country_id وphone.
3. تحقق بالـchallenge والرقم والدولة نفسهما.
4. نجاح التغيير يسحب جميع REST tokens واشتراكات Push؛ أعد الدخول بالرقم الجديد.

Admin وVendor يملكان مساري تغيير الهاتف، لكن لا يملكان مسارات client/auth/reauthenticate. فقدان الرقم لدى Client يذهب للدعم؛ لا توجد استعادة بالبريد.

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/client/auth/reauthenticate/request` |
| `POST` | `/api/v1/client/auth/reauthenticate/verify` |
| `POST` | `/api/v1/client/profile/phone/request` |
| `POST` | `/api/v1/client/profile/phone/verify` |

## أمثلة التدفق بالتفصيل

تغيير الهاتف وإغلاق الحساب يحتاجان تحققًا خلال آخر 5 دقائق افتراضيًا. دخول OTP حديث يحقق ذلك. عند `403 REAUTHENTICATION_REQUIRED` اتبع الخطوتين التاليتين، ثم أعد العملية الأصلية. صلاحية التحقق مرتبطة بالتوكن الحالي فقط.

### 6.1 كود للهاتف الحالي — POST /auth/reauthenticate/request

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/auth/reauthenticate/request" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "challenge_id": "33333333-3333-4333-8333-333333333333",
    "expires_in": 300,
    "resend_after": 60
  }
}
```

لا يحتاج Body. الوجهة هي الهاتف الموثق الموجود في الحساب؛ لا يستطيع الطلب اختيار هاتف بديل. لاحظ أن message هنا null.

### 6.2 تأكيد الجلسة — POST /auth/reauthenticate/verify

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/auth/reauthenticate/verify" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"challenge_id":"33333333-3333-4333-8333-333333333333","code":"234567"}'
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "valid_until": "2026-10-11T10:15:00+00:00"
  }
}
```

### 6.3 كود للهاتف الجديد — POST /profile/phone/request

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/profile/phone/request" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"country_id":1,"phone":"+201112345678"}'
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": "A verification code has been sent.",
  "data": {
    "challenge_id": "44444444-4444-4444-8444-444444444444",
    "expires_in": 300,
    "resend_after": 60
  }
}
```

الكود يُرسل للهاتف الجديد. الرقم المستخدم بحساب آخر، بما في ذلك حساب مغلق، يسبب `409 PHONE_ALREADY_EXISTS`. يستخدم التحقق الحالي والجديد غرضين منفصلين؛ لا يمكن تبديل أكوادهما.

### 6.4 تثبيت الهاتف — POST /profile/phone/verify

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/profile/phone/verify" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"country_id":1,"phone":"+201112345678","challenge_id":"44444444-4444-4444-8444-444444444444","code":"345678"}'
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": "Your phone number has been changed. Please sign in again.",
  "data": {
    "id": 42,
    "name": "Ahmed Ali",
    "phone": "+201112345678",
    "phone_country_id": 1,
    "phone_verified_at": "2026-10-11T10:12:00+00:00",
    "type": "client",
    "status": "active",
    "locale": "en",
    "timezone": "Africa/Cairo",
    "has_avatar": false,
    "avatar_url": null,
    "profile_completed": true,
    "terms_version": "1"
  }
}
```

يُعاد فحص التحقق الحديث عند هذه الخطوة أيضًا. النجاح يسحب **جميع** التوكنات واشتراكات Push، بما فيها توكن الطلب نفسه، ويسجل إشعارًا أمنيًا. امسح الحالة المحلية وافصل Socket ثم سجل الدخول بـOTP إلى الرقم الجديد وأعد تسجيل FCM. لا يوجد توكن بديل في الاستجابة.

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [ReauthenticationController.php](../../app/Http/Controllers/Client/ReauthenticationController.php)
- [PhoneChangeController.php](../../app/Http/Controllers/Shared/PhoneChangeController.php)
