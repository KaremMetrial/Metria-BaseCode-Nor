# Client — الدخول والتسجيل

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. جهّز هوية الحساب وبيئة SMS/الدخول.
2. نفّذ طلب الدخول المناسب لنوع الحساب واحفظ التوكن الراجع كاملًا.
3. اقرأ الملف والحالة قبل فتح الميزات المحمية.
4. عند 401 أوقف الطلبات المحمية وارجع للدخول؛ لا يوجد refresh-token endpoint.

## المسارات

| Method   | Endpoint                                 |
| -------- | ---------------------------------------- |
| `GET`  | `/api/v1/client/auth/requirements`     |
| `POST` | `/api/v1/client/auth/otp/request`      |
| `POST` | `/api/v1/client/auth/otp/verify`       |
| `POST` | `/api/v1/client/auth/complete-profile` |

## أمثلة التدفق بالتفصيل

التسلسل: المتطلبات والدول → إرسال OTP → التحقق → حفظ التوكن → اتباع `next_step` → تسجيل الجهاز وتحميل الملف والإشعارات.

### 3.1 قراءة المتطلبات — GET /auth/requirements

```sh
curl --fail-with-body -X GET "$CLIENT_BASE/auth/requirements" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en'
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "terms_version": "1",
    "terms_revision_id": null,
    "terms_url": null,
    "recovery": {
      "method": "support",
      "support_url": null
    }
  }
}
```

اعرض رابط الشروط الذي تضبطه البيئة. أرسل نفس `terms_version` عند إكمال الحساب؛ لا تثبّت الإصدار داخل التطبيق.

### 3.2 اختيار الدولة — GET /api/v1/locations/countries

```sh
curl --fail-with-body "$API_BASE/locations/countries" \
  -H 'Accept: application/json' -H 'X-Locale: en'
```

الاستجابة: **HTTP 200**.

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
      "phone_example": "01012345678"
    }
  ]
}
```

القائمة غير مقسمة إلى صفحات وتحتوي الدول النشطة فقط. افترضنا هنا أن مصر ID=1؛ استخدم ID من الاستجابة الفعلية. `phone_example` مجرد مساعدة للواجهة؛ السيرفر يتحقق من الرقم ويطبّعه إلى E.164.

### 3.3 إرسال الكود — POST /auth/otp/request

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/auth/otp/request" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H 'Content-Type: application/json' \
  --data '{"country_id":1,"phone":"+201012345678"}'
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": "A verification code has been sent.",
  "data": {
    "challenge_id": "11111111-1111-4111-8111-111111111111",
    "expires_in": 300,تم إنشاء
    "resend_after": 60
  }
}
```

احفظ `challenge_id` وشغّل مؤقتي الانتهاء وإعادة الإرسال من الاستجابة. الكود يصل عبر SMS ولا يظهر في API. إعادة الإرسال تستخدم نفس endpoint، وبعد نجاحها استخدم الـchallenge الجديد. لا تبدأ جلسة قبل التحقق.

### 3.4 التحقق — POST /auth/otp/verify

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/auth/otp/verify" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H 'Content-Type: application/json' \
  --data '{"country_id":1,"phone":"+201012345678","challenge_id":"11111111-1111-4111-8111-111111111111","code":"123456","device_name":"Ahmed iPhone","device_id":"22222222-2222-4222-8222-222222222222"}'
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": "You have signed in successfully.",
  "data": {
    "user": {
      "id": 42,
      "name": "",
      "phone": "+201012345678",
      "phone_country_id": 1,
      "phone_verified_at": "2026-10-11T10:00:00+00:00",
      "type": "client",
      "status": "active",
      "locale": "en",
      "timezone": null,
      "has_avatar": false,
      "avatar_url": null,
      "profile_completed": false,
      "terms_version": null
    },
    "token": "42|REPLACE_WITH_RETURNED_TOKEN",
    "expires_at": "2026-10-12T10:00:00+00:00",
    "next_step": "complete_profile"
  }
}
```

`code` نص من 6 أرقام للحفاظ على الصفر في البداية. `device_name` اختياري حتى 100 حرف؛ `device_id` UUID اختياري يُنشأ لكل تثبيت ويُحفظ لإعادة استخدامه. ليس FCM token. إعادة الدخول بنفس `device_id` تسحب جلسة ذلك التثبيت السابقة واشتراك Push المرتبط بها. الحد الافتراضي 10 جلسات؛ إنشاء جلسة إضافية يسحب الأقدم.

للمستخدم الجديد يُنشأ حساب نشط ومحفظة بالعملة الافتراضية وإشعار ترحيب. للدخول اللاحق يُسجل إشعار دخول جديد. خزّن التوكن في مخزن مناسب للمنصة ولا تطبعه في السجلات. الاستجابة لا تحتوي Email.

إذا كان الملف مكتملًا وإصدار الشروط الحالي مقبولًا، يرجع نفس الشكل مع بيانات المستخدم الحالية و`next_step: "home"`. إذا تغير إصدار الشروط أو لم يكتمل الملف، يرجع `complete_profile`. هذا توجيه لواجهة التطبيق؛ ليس بوابة عامة تمنع كل عمليات الحساب النشط في الـBackend.

### 3.5 إكمال التسجيل — POST /auth/complete-profile

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/auth/complete-profile" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"name":"Ahmed Ali","locale":"en","timezone":"Africa/Cairo","terms_accepted":true,"terms_version":"1"}'
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "user": {
      "id": 42,
      "name": "Ahmed Ali",
      "phone": "+201012345678",
      "phone_country_id": 1,
      "phone_verified_at": "2026-10-11T10:00:00+00:00",
      "type": "client",
      "status": "active",
      "locale": "en",
      "timezone": "Africa/Cairo",
      "has_avatar": false,
      "avatar_url": null,
      "profile_completed": true,
      "terms_version": "1"
    },
    "next_step": "home"
  }
}
```

`name` مطلوب حتى 255 حرفًا، و`terms_accepted` يجب أن يدل على الموافقة، و`terms_version` يجب أن يطابق متطلبات السيرفر. `locale` اختياري (`en` أو `ar`) و`timezone` اختياري أو `null`. عند رفض إصدار قديم بـ 422، اجلب المتطلبات واعرض الشروط الحالية قبل الإرسال مجددًا. تكرار نفس الإكمال لا يغيّر تاريخ الموافقة الأصلي لنفس الإصدار.

### شروط منشورة ومدارة من الأدمن

عندما يعيد requirements قيمة غير null في `terms_revision_id`، أرسلها مع `terms_version` عند complete-profile. مثال: `{"terms_version":"terms-1-2","terms_revision_id":12,"terms_accepted":true,"name":"Ahmed Ali"}`. لا تثبّت هذه القيم؛ يجب أن تكون من نفس نسخة الشروط المعروضة. المسار يدعم الحسابات القديمة قبل أول نشر باستخدام version من الإعدادات.

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [AccountController.php](../../app/Http/Controllers/Client/AccountController.php)
- [OnboardingController.php](../../app/Http/Controllers/Client/OnboardingController.php)
- [OtpController.php](../../app/Http/Controllers/Shared/OtpController.php)
