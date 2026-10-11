# Vendor — أجهزة Push وتفضيلات الإشعارات

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اطلب إذن الإشعارات من المستخدم في التطبيق واحصل على FCM token.
2. سجّل التوكن على الجلسة الحالية بعد نجاح الدخول؛ عند تدويره سجله مرة أخرى.
3. اقرأ preferences ثم PATCH بالمفاتيح المطلوبة فقط.
4. احذف تسجيل الجهاز قبل تبديل الحساب. Logout وسحب الجلسة يوقفان التسليم أيضًا.

حد الأجهزة 10. device_id في OTP هو معرّف تثبيت UUID، وليس FCM token. token مسجل لحساب آخر يعيد 409. التسليم الخارجي يحتاج إعداد FCM ولا يتم إثباته بمجرد نجاح التسجيل.

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/vendor/devices` |
| `DELETE` | `/api/v1/vendor/devices/{device}` |
| `GET` | `/api/v1/vendor/notification-preferences` |
| `PATCH` | `/api/v1/vendor/notification-preferences` |

## `POST /api/v1/vendor/devices`

Registers a Firebase device token for the current bearer session; token rotation replaces its previous subscription. Logout or session expiry stops delivery. A token owned by another account returns 409 RESOURCE_CONFLICT. Each account may register up to ten devices.

Requires a bearer token for the Vendor actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Additional rate limit: 5 requests/minute per IP.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `token` | string | نعم | minLength: 20; maxLength: 4096; pattern: "^[A-Za-z0-9_:\\-]+$"  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/vendor/devices" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"token":"REPLACE_WITH_DEVICE_FCM_TOKEN"}'
```

### مثال الاستجابة — HTTP 201

```json
{
  "success": true,
  "message": "The device has been registered.",
  "data": {
    "id": 42
  }
}
```

## `DELETE /api/v1/vendor/devices/{device}`

Deletes a device owned by the caller. A different account’s device is not visible.

Requires a bearer token for the Vendor actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `device` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X DELETE "$API_BASE/vendor/devices/12" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": "The device has been removed.",
  "data": null
}
```

## `GET /api/v1/vendor/notification-preferences`

Marketing push is disabled until explicitly enabled. Security notifications always remain available in the notification center.

Requires a bearer token for the Vendor actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/vendor/notification-preferences" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "preferences": {
      "security_push": true,
      "transactional_push": true,
      "marketing_push": false
    }
  }
}
```

## `PATCH /api/v1/vendor/notification-preferences`

Merges only supplied channel preferences. Pending deliveries recheck these preferences before sending.

Requires a bearer token for the Vendor actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `preferences` | object | نعم |   |
| `preferences.security_push` | boolean | نعم |   |
| `preferences.transactional_push` | boolean | نعم |   |
| `preferences.marketing_push` | boolean | نعم |   |

### مثال الطلب

```sh
curl --fail-with-body -X PATCH "$API_BASE/vendor/notification-preferences" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"preferences":{"security_push":true,"transactional_push":true,"marketing_push":false}}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "preferences": {
      "security_push": true,
      "transactional_push": true,
      "marketing_push": false
    }
  }
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [DeviceTokenController.php](../../app/Http/Controllers/Shared/DeviceTokenController.php)
- [NotificationPreferenceController.php](../../app/Http/Controllers/Shared/NotificationPreferenceController.php)
