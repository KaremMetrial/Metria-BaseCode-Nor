# Client — أجهزة Push وتفضيلات الإشعارات

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
| `POST` | `/api/v1/client/devices` |
| `DELETE` | `/api/v1/client/devices/{device}` |
| `GET` | `/api/v1/client/notification-preferences` |
| `PATCH` | `/api/v1/client/notification-preferences` |

## أمثلة التدفق بالتفصيل

### 7.1 تسجيل الجهاز — POST /devices

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/devices" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"token":"example_fcm_registration_token_1234567890"}'
```

الاستجابة: **HTTP 201**.

```json
{
  "success": true,
  "message": "The device has been registered.",
  "data": {
    "id": 7
  }
}
```

استبدل المثال بتوكن حقيقي من Firebase SDK. الحقل نص بطول 20–4096 من الحروف والأرقام و`_:-`. يحتفظ التطبيق بـ`data.id` للحذف لاحقًا.

سجل الجهاز بعد الدخول باستخدام توكن الجلسة الجديدة، وكرر التسجيل عند تغير FCM token. يرتبط الاشتراك بالجلسة الحالية؛ تدوير FCM يستبدل اشتراكها السابق. التوكن المسجل لمستخدم آخر يُرفض بـ 409 RESOURCE_CONFLICT. عند تبديل الحساب نفذ الخروج من الحساب السابق قبل ربط الجهاز بالجديد. الحد الأقصى 10 أجهزة للحساب. الاشتراكات القديمة غير المرتبطة بجلسة تحتاج إعادة تسجيل.

رفض المستخدم إذن Push لا يمنع الدخول أو قراءة صندوق الإشعارات. سحب الجلسة يمنع التسليم المستقبلي عبر اشتراكها؛ رسالة سبق إرسالها للمزود قد تصل لاحقًا.

### 7.2 إلغاء Push للجهاز — DELETE /devices/{device}

```sh
curl --fail-with-body -X DELETE "$CLIENT_BASE/devices/7" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": "The device has been removed.",
  "data": null
}
```

يحذف الاشتراك ولا يسحب جلسة الدخول. استخدم ID الخاص بك؛ ID غير موجود أو مملوك لغيرك يرجع 404.

### 7.3 قراءة التفضيلات — GET /notification-preferences

```sh
curl --fail-with-body -X GET "$CLIENT_BASE/notification-preferences" \
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
    "preferences": {
      "security_push": true,
      "transactional_push": true,
      "marketing_push": false
    }
  }
}
```

### 7.4 تحديث التفضيلات — PATCH /notification-preferences

```sh
curl --fail-with-body -X PATCH "$CLIENT_BASE/notification-preferences" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"preferences":{"marketing_push":true}}'
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "preferences": {
      "security_push": true,
      "transactional_push": true,
      "marketing_push": true
    }
  }
}
```

التحديث جزئي ويدمج الحقول المرسلة فقط. أسماء أخرى داخل `preferences` تُرفض بـ 422. تعطيل `security_push` يعطل قناة Push فقط؛ يبقى الإشعار الأمني داخل الصندوق. لا توجد رسائل Email للعميل حتى إذا كانت بيانات قديمة تحتوي بريدًا موثقًا. تفعيل القناة لاحقًا لا يعيد إرسال الإشعارات التي تم استبعادها سابقًا.

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [DeviceTokenController.php](../../app/Http/Controllers/Shared/DeviceTokenController.php)
- [NotificationPreferenceController.php](../../app/Http/Controllers/Shared/NotificationPreferenceController.php)
