# دليل دمج Client API — الحساب والإشعارات

> التوثيق المقسّم حسب الميزة والحساب: [docs/README.md](docs/README.md) — يشمل التدفقات الكاملة وأمثلة الطلبات والردود.

دليل عملي لمطور تطبيق العميل (Web / Mobile)، مطابق للمسارات والـControllers والـResources الحالية. يشمل تدفق الحساب والإشعارات وعمليات المحفظة والدفع، مع تدفق الحساب والإشعارات بالتفصيل وملحق المحفظة والدفع. العميل يستخدم **رقم الهاتف فقط**؛ لا يحتاج Email أو Password.

الأمثلة بيانات توضيحية واقعية الشكل، وليست نتائج طلبات حية أو بيانات دخول صالحة. استبدل أرقام الهواتف بأرقام تملكها، وIDs والتوكنات والأكواد بالقيم التي يرجعها بيئتك. الأمثلة مستقلة عند الحاجة؛ لا تنفّذ حذف الحساب أو سحب الجلسات أثناء تجربة بقية الخطوات.

لإدارة الصفحات والشروط المنشورة ونموذج «تواصل معنا» وشات الدعم، راجع [دليل الصفحات والتواصل والدعم](API.md#content-pages-contact-form-and-support-chat). أصبحت `terms_revision_id` مطلوبة عند الموافقة على شروط منشورة من نظام الصفحات؛ أرسل revision_id من النص الذي عرضته، مع terms_version. قبل أول نشر تبقى إعدادات الشروط القديمة فعالة ويكون terms_revision_id=null.

## المحتويات

1. [الإعداد والعقد المشترك](#setup)
2. [خريطة المسارات](#endpoints)
3. [التسجيل والدخول وإكمال الحساب](#authentication)
4. [الملف الشخصي والصورة](#profile)
5. [الجلسات والخروج](#sessions)
6. [التحقق الحديث وتغيير الهاتف](#phone)
7. [تسجيل Push وتفضيلات الإشعارات](#push)
8. [صندوق الإشعارات والمزامنة](#notifications)
9. [Socket.IO](#realtime)
10. [إغلاق الحساب وفقدان الهاتف](#closure)
11. [المحفظة والدفع](#payments)
12. [الأخطاء وحدود الطلبات](#errors)
13. [ترتيب التنفيذ في التطبيق](#integration)

<a id="setup"></a>
## 1. الإعداد والعقد المشترك

اضبط عنوان التطبيق بدون `/` في النهاية. `api.example.com` عنوان توضيحي؛ استبدله بعنوان بيئة التطوير أو النشر:

```sh
export APP_BASE='https://api.example.com'
export API_BASE="$APP_BASE/api/v1"
export CLIENT_BASE="$API_BASE/client"
# بعد نجاح OTP: خزّن data.token كاملاً، بما فيه الرقم وعلامة |.
export TOKEN='42|REPLACE_WITH_RETURNED_TOKEN'
```

جميع المسارات أدناه تبدأ بـ`$CLIENT_BASE`، إلا مسار الدول العام. الطلبات المحمية تتطلب `Authorization: Bearer ...`. استخدم `Accept: application/json`، و`Content-Type: application/json` مع جسم JSON فقط. رفع الصورة يستخدم multipart.

الأمثلة ترسل `X-Locale: en` لتثبيت رسائل الاستجابة. استخدم `ar` للعربية. أولوية اللغة: `locale` في Query ثم `X-Locale` ثم تفضيل المستخدم ثم `Accept-Language` ثم لغة التطبيق. الرد يحمل `Content-Language`. قد تبقى لغة إشعار قديم كما كانت عند إنشائه حتى بعد تغيير لغة الملف الشخصي.

`message` قد تكون `null`. اتخذ قرارات التطبيق من HTTP status و`code` وليس من نص الرسالة. التواريخ العادية ISO 8601؛ استثناء Socket token يعيد `expires_at` كـUnix seconds. يمكن إرسال `X-Request-ID` للتتبع (8–64 حرفًا من الحروف والأرقام و`._-`)؛ احتفظ بقيمة الهيدر الراجعة عند عرض مشكلة للدعم.

شكل النجاح:

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

شكل الخطأ:

```json
{
  "success": false,
  "code": "UNAUTHENTICATED",
  "message": "You are not authenticated. Please sign in.",
  "errors": {}
}
```

التوكن خاص بمستخدم ونوع حساب؛ توكن Vendor/Admin لا يمنح الوصول لمسارات Client. الموارد المملوكة لغير المستخدم تعيد 404 على المسارات التي تتحقق من الملكية. لا ترسل `user_id` لاختيار صاحب الحساب.

قبل الدمج يجب أن تكون migrations مطبقة، وSMS provider مهيأ. Push يحتاج إعداد FCM، وRealtime يحتاج Socket.IO وRedis وسرًا مشتركًا. يجب تشغيل Queue worker وScheduler لتسليم الإشعارات. قيم `CLIENT_TERMS_VERSION` و`CLIENT_TERMS_URL` و`CLIENT_SUPPORT_URL` يضبطها مسؤول البيئة. عدم تهيئة الروابط يظهر كـ`null`، وليس كرابط افتراضي.

<a id="endpoints"></a>
## 2. خريطة المسارات

`Public` = بدون توكن. `Token` = توكن Client صالح. `Active` = توكن Client وحساب نشط. `Recent` = Active مع تحقق هاتف خلال آخر 5 دقائق افتراضيًا؛ تسجيل الدخول يُحتسب تحققًا حديثًا.

| Method | المسار بعد `/api/v1/client` | الوصول | الاستخدام |
| --- | --- | --- | --- |

| GET | /auth/requirements | Public | الشروط ومسار الدعم |

| POST | /auth/otp/request | Public | إرسال كود الدخول/التسجيل |

| POST | /auth/otp/verify | Public | التحقق وإنشاء الجلسة |

| POST | /auth/complete-profile | Active | الاسم والموافقة على الشروط |

| GET | /profile | Token | قراءة الملف |

| PATCH | /profile | Active | تحديث الاسم واللغة والمنطقة الزمنية |

| POST | /profile/avatar | Active | رفع الصورة |

| DELETE | /profile/avatar | Active | إزالة الصورة |

| GET | /auth/sessions | Token | الجلسات السارية |

| DELETE | /auth/sessions/{session} | Token | سحب جلسة |

| POST | /auth/logout | Token | الخروج من الجلسة الحالية |

| POST | /auth/logout-all | Token | الخروج من الجميع |

| POST | /auth/reauthenticate/request | Active | كود إلى الهاتف الحالي |

| POST | /auth/reauthenticate/verify | Active | تأكيد حديث للجلسة الحالية |

| POST | /profile/phone/request | Recent | كود إلى الهاتف الجديد |

| POST | /profile/phone/verify | Recent | تغيير الهاتف وسحب كل الجلسات |

| POST | /devices | Active | تسجيل/تحديث FCM token |

| DELETE | /devices/{device} | Active | إلغاء اشتراك Push |

| GET | /notification-preferences | Active | قراءة التفضيلات |

| PATCH | /notification-preferences | Active | تحديث التفضيلات |

| GET | /notifications | Active | الصندوق أو مزامنة التغييرات |

| GET | /notifications/unread-count | Active | العدد والإصدار الحاليان |

| PATCH | /notifications/{notification}/read | Active | قراءة إشعار |

| PATCH | /notifications/read-all | Active | قراءة الجميع حتى snapshot محدد |

| PATCH | /notifications/{notification}/archive | Active | أرشفة إشعار |

| POST | /realtime/token | Active | إصدار اعتماد Socket.IO |

| DELETE | /account | Recent | إغلاق الحساب |

| GET | /wallet | Active | المحافظ |

| GET | /wallet/{wallet}/transactions | Active | حركات المحفظة |

| POST | /payments | Active | إنشاء دفعة مع Idempotency-Key |

| GET | /payments | Active | قائمة الدفعات |

| GET | /payments/{payment} | Active | حالة دفعة |

لا يوجد Endpoint مستقل باسم register أو refresh-token أو forgot-password. طلب OTP والتحقق منه يخدمان التسجيل والدخول. انتهاء التوكن يستلزم الدخول مجددًا.

<a id="authentication"></a>
## 3. التسجيل والدخول وإكمال الحساب

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
    "expires_in": 300,
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

`name` مطلوب حتى 255 حرفًا، و`terms_accepted` يجب أن يدل على الموافقة، و`terms_version` يجب أن يطابق متطلبات السيرفر. `locale` اختياري (`en` أو `ar`) و`timezone` اختياري أو `null`. عند رفض إصدار قديم بـ422، اجلب المتطلبات واعرض الشروط الحالية قبل الإرسال مجددًا. تكرار نفس الإكمال لا يغيّر تاريخ الموافقة الأصلي لنفس الإصدار.

<a id="profile"></a>
## 4. الملف الشخصي والصورة

### 4.1 قراءة الملف — GET /profile

```sh
curl --fail-with-body -X GET "$CLIENT_BASE/profile" \
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
  }
}
```

استخدمه عند استعادة التطبيق للتحقق من الجلسة وتحديث بيانات المستخدم. يمكن قراءة الملف بتوكن صالح حتى للحساب غير النشط، بينما تعديلات الملف تتطلب حسابًا نشطًا.

### 4.2 تحديث الملف — PATCH /profile

```sh
curl --fail-with-body -X PATCH "$CLIENT_BASE/profile" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"name":"Ahmed Hassan","locale":"en","timezone":"Africa/Cairo"}'
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": "Your profile has been updated.",
  "data": {
    "id": 42,
    "name": "Ahmed Hassan",
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
  }
}
```

أرسل الحقول المتغيرة فقط. يمكن مسح timezone بـ`null`. حقول الهاتف والبريد وكلمة المرور والنوع والحالة والصلاحيات والرصيد محظورة هنا. تغيير الهاتف له تدفق منفصل.

### 4.3 رفع الصورة — POST /profile/avatar

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/profile/avatar" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN" \
  -F "avatar=@/absolute/path/avatar.jpg"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 42,
    "name": "Ahmed Ali",
    "phone": "+201012345678",
    "phone_country_id": 1,
    "phone_verified_at": "2026-10-11T10:00:00+00:00",
    "type": "client",
    "status": "active",
    "locale": "en",
    "timezone": "Africa/Cairo",
    "has_avatar": true,
    "avatar_url": "https://api.example.com/storage/avatars/example.jpg?expires=1791713400&signature=EXAMPLE",
    "profile_completed": true,
    "terms_version": "1"
  }
}
```

JPEG/PNG/WebP فقط، حتى 2048KB وبُعد أقصى 2048×2048. لا تضبط Content-Type يدويًا؛ curl ينشئ multipart boundary. الصورة خاصة والرابط المؤقت صالح 5 دقائق. الرابط أعلاه تمثيلي: استخدم قيمة `avatar_url` كما هي، وأعد جلب الملف لتجديدها؛ لا تبنِ المسار أو التوقيع بنفسك.

### 4.4 حذف الصورة — DELETE /profile/avatar

```sh
curl --fail-with-body -X DELETE "$CLIENT_BASE/profile/avatar" \
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
  }
}
```

تكرار الحذف آمن؛ النتيجة `has_avatar: false` و`avatar_url: null`.

<a id="sessions"></a>
## 5. الجلسات والخروج

كل جلسة تمثل Bearer token. `device_id` الخاص بالتثبيت وID سجل Push وID الجلسة ثلاث قيم مختلفة.

### 5.1 الجلسات — GET /auth/sessions

```sh
curl --fail-with-body -X GET "$CLIENT_BASE/auth/sessions" \
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
    "items": [
      {
        "id": 42,
        "device_name": "Ahmed iPhone",
        "is_current": true,
        "created_at": "2026-10-11T10:00:00+00:00",
        "last_used_at": "2026-10-11T10:02:00+00:00",
        "expires_at": "2026-10-12T10:00:00+00:00"
      },
      {
        "id": 41,
        "device_name": "Ahmed Android",
        "is_current": false,
        "created_at": "2026-10-11T09:00:00+00:00",
        "last_used_at": null,
        "expires_at": "2026-10-12T09:00:00+00:00"
      }
    ]
  }
}
```

القائمة الأحدث أولًا وتستبعد الجلسات المنتهية؛ لا تحتوي أسرار التوكنات.

### 5.2 سحب جلسة — DELETE /auth/sessions/{session}

```sh
curl --fail-with-body -X DELETE "$CLIENT_BASE/auth/sessions/41" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

استخدم ID من قائمة الجلسات. تُحذف معها اشتراكات Push التابعة لها. إذا سحبت الجلسة الحالية امسح التوكن المحلي وافصل Socket. جلسة مستخدم آخر أو جلسة محذوفة تعيد 404.

### 5.3 خروج الجهاز الحالي — POST /auth/logout

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/auth/logout" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": "You have signed out.",
  "data": null
}
```

يسحب التوكن الحالي واشتراك Push المرتبط به. بعد النجاح احذف بيانات المستخدم المحلية وافصل Socket. إعادة الطلب بنفس التوكن المسحوب ترجع 401. إذا فشل الاتصال، الخروج المحلي وحده لا يثبت سحب التوكن من السيرفر.

### 5.4 الخروج من الجميع — POST /auth/logout-all

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/auth/logout-all" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

كل جلسات المستخدم واشتراكات Push تُسحب، بما فيها الحالية. طبّق تنظيف الحالة المحلية نفسه. لا تحتاج OTP حديثًا للخروج أو لسحب الجلسات.

<a id="phone"></a>
## 6. التحقق الحديث وتغيير الهاتف

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

<a id="push"></a>
## 7. تسجيل Push وتفضيلات الإشعارات

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

سجل الجهاز بعد الدخول باستخدام توكن الجلسة الجديدة، وكرر التسجيل عند تغير FCM token. يرتبط الاشتراك بالجلسة الحالية؛ تدوير FCM يستبدل اشتراكها السابق. التوكن المسجل لمستخدم آخر يُرفض بـ409 RESOURCE_CONFLICT. عند تبديل الحساب نفذ الخروج من الحساب السابق قبل ربط الجهاز بالجديد. الحد الأقصى 10 أجهزة للحساب. الاشتراكات القديمة غير المرتبطة بجلسة تحتاج إعادة تسجيل.

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

التحديث جزئي ويدمج الحقول المرسلة فقط. أسماء أخرى داخل `preferences` تُرفض بـ422. تعطيل `security_push` يعطل قناة Push فقط؛ يبقى الإشعار الأمني داخل الصندوق. لا توجد رسائل Email للعميل حتى إذا كانت بيانات قديمة تحتوي بريدًا موثقًا. تفعيل القناة لاحقًا لا يعيد إرسال الإشعارات التي تم استبعادها سابقًا.

<a id="notifications"></a>
## 8. صندوق الإشعارات والمزامنة

### 8.1 عرض الصندوق — GET /notifications

```sh
curl --fail-with-body -X GET "$CLIENT_BASE/notifications?status=unread&category=security&per_page=25&page=1" \
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
    "items": [
      {
        "id": "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa",
        "category": "security",
        "action": {
          "screen": "sessions"
        },
        "archived_at": null,
        "revision": 1,
        "title": "Account notification",
        "body": "Welcome! Complete your profile to get started.",
        "read_at": null,
        "created_at": "2026-10-11T10:00:00+00:00"
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 25,
      "total": 1,
      "snapshot_version": 1
    },
    "unread_count": 1
  }
}
```

Query parameters:

| الحقل | القيم / الافتراضي |
| --- | --- |
| `status` | `all` افتراضيًا، أو `read` أو `unread` |
| `category` | اختياري: `security` أو `transactional` أو `marketing` |
| `per_page` | 1–100؛ الافتراضي 25 |
| `page` | يبدأ من 1 |

القائمة تستبعد المؤرشف، والأحدث أولًا. `unread_count` يخص الصندوق كله وليس الفلتر الحالي. `revision` إصدار آخر تعديل للسجل و`meta.snapshot_version` إصدار حالة المستخدم عند القراءة. احفظ نسخة snapshot عند تنفيذ read-all.

`action` قد تكون null أو object مثل `{"screen":"sessions"}`. اربط الشاشات المعروفة بقائمة مسموحة داخل التطبيق؛ إذا ظهر نوع غير معروف اعرض الإشعار بدون تنقل. `read_at` يعني قراءة داخل التطبيق، وليس وصول Push.

### 8.2 العدد الحالي — GET /notifications/unread-count

```sh
curl --fail-with-body -X GET "$CLIENT_BASE/notifications/unread-count" \
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
    "unread_count": 1,
    "version": 1
  }
}
```

استخدم العدد المرجعي لشارة التطبيق. وصول version أعلى يخبرك بوجود تغييرات؛ لا تعتبره آخر إصدار تمت مزامنته محليًا قبل تنزيل التغييرات نفسها.

### 8.3 قراءة إشعار — PATCH /notifications/{notification}/read

```sh
curl --fail-with-body -X PATCH "$CLIENT_BASE/notifications/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa/read" \
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
    "id": "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa",
    "category": "security",
    "action": {
      "screen": "sessions"
    },
    "archived_at": null,
    "revision": 2,
    "title": "Account notification",
    "body": "Welcome! Complete your profile to get started.",
    "read_at": "2026-10-11T10:20:00+00:00",
    "created_at": "2026-10-11T10:00:00+00:00"
  }
}
```

بدون Body. كرر الطلب بأمان إذا انقطع الاتصال؛ تاريخ القراءة الأصلي لا يتغير. الاستجابة سجل الإشعار مباشرة داخل data، وليس data.item.

### 8.4 قراءة الجميع — PATCH /notifications/read-all

```sh
curl --fail-with-body -X PATCH "$CLIENT_BASE/notifications/read-all" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"through_version":1}'
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "updated": 1,
    "unread_count": 0,
    "version": 2
  }
}
```

مثال مستقل عن 8.3: كانت هناك رسالة غير مقروءة عند snapshot=1. أرسل `through_version` من آخر snapshot معروض، وليس رقمًا تقديريًا. يتأثر الصندوق كله حتى ذلك الإصدار، وليس الفئة المفلترة فقط. إشعار جديد بعد snapshot يبقى غير مقروء. تكرار الطلب يرجع updated=0 إذا لم يبق شيء مستحق للقراءة؛ version قد يتغير بسبب نشاط آخر.

### 8.5 الأرشفة — PATCH /notifications/{notification}/archive

```sh
curl --fail-with-body -X PATCH "$CLIENT_BASE/notifications/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa/archive" \
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
    "id": "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa",
    "category": "security",
    "action": {
      "screen": "sessions"
    },
    "archived_at": "2026-10-11T10:21:00+00:00",
    "revision": 3,
    "title": "Account notification",
    "body": "Welcome! Complete your profile to get started.",
    "read_at": "2026-10-11T10:20:00+00:00",
    "created_at": "2026-10-11T10:00:00+00:00"
  }
}
```

يختفي الإشعار من الصندوق العادي ومن عدد غير المقروء. لا يوجد endpoint لاستعادة المؤرشف. التكرار يحافظ على تاريخ الأرشفة. سجل الأرشفة يبقى في المزامنة ليزيل الجهاز الآخر النسخة المحلية.

### 8.6 مزامنة تغييرات الأجهزة — GET /notifications?since_version=N

```sh
curl --fail-with-body -X GET "$CLIENT_BASE/notifications?since_version=1&per_page=25" \
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
    "items": [
      {
        "id": "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa",
        "category": "security",
        "action": {
          "screen": "sessions"
        },
        "archived_at": "2026-10-11T10:21:00+00:00",
        "revision": 3,
        "title": "Account notification",
        "body": "Welcome! Complete your profile to get started.",
        "read_at": "2026-10-11T10:20:00+00:00",
        "created_at": "2026-10-11T10:00:00+00:00"
      }
    ],
    "meta": {
      "next_cursor": null,
      "snapshot_version": 3
    },
    "unread_count": 0
  }
}
```

في هذا الوضع تُعاد أحدث حالة للسجلات التي `revision > since_version` حتى snapshot المحدد، بما فيها المؤرشف. ليست قائمة بكل الأحداث التاريخية. الترتيب حسب revision ثم ID. فلاتر status/category لا تُطبق في وضع المزامنة. شكل meta مختلف عن قائمة الصندوق.

خوارزمية العميل:

1. للتحميل الأول اجلب الصندوق العادي (بدون since_version)، واحفظ snapshot أول صفحة كنقطة بداية. أكمل الصفحات المطلوبة، وادمج بالـID. السجلات القديمة قبل إضافة الإصدارات قد تحمل revision=0 ولا تظهر في طلب since_version=0؛ لذلك لا تستبدل التحميل الأول بمزامنة فقط.
2. نفذ المزامنة من الإصدار المحفوظ. احفظ snapshot أول استجابة لهذه الجولة.
3. إذا كان next_cursor غير null، استخدمه مع **نفس** since_version و`until_version` مساويًا للـsnapshot المحفوظ. لا تستخدم page في هذه الجولة.
4. ادمج السجل بحسب ID وrevision؛ لا تستبدل نسخة أحدث بأقدم. عند archived_at غير null احذفه من عرض الصندوق مع حفظ ما يلزم لمنع إعادته بنسخة قديمة.
5. بعد اكتمال كل الصفحات فقط، احفظ snapshot كآخر إصدار مزامن. إن فشلت صفحة احتفظ بالإصدار السابق وأعد المحاولة. شغّل جولة لاحقة لالتقاط تغييرات أثناء التحميل.
6. شغّل المزامنة عند فتح التطبيق وعودة الاتصال وحدث Socket، مع منع جولتين متداخلتين لنفس الحساب. إذا فقدت النسخة المحلية امسح الإصدار المحفوظ وأعد التحميل الأول.

مثال طلب الصفحة التالية، بالقيم المأخوذة من الجولة الفعلية:

```sh
export SINCE_VERSION='1'
export SNAPSHOT_VERSION='3'
export NEXT_CURSOR='REPLACE_WITH_RETURNED_NEXT_CURSOR'
curl --fail-with-body --get "$CLIENT_BASE/notifications" \
  -H 'Accept: application/json' -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN" \
  --data-urlencode "since_version=$SINCE_VERSION" \
  --data-urlencode "until_version=$SNAPSHOT_VERSION" \
  --data-urlencode "cursor=$NEXT_CURSOR" \
  --data-urlencode 'per_page=25'
```

الاستجابة بنفس شكل المزامنة السابق؛ next_cursor=null يعني انتهاء الجولة. يمكن أن تكون items فارغة مع snapshot أحدث. لا تزيد العداد محليًا على كل حدث؛ التكرار وتأخر التسليم ممكنان، لذا استعمل العدد المرجعي.

<a id="realtime"></a>
## 9. Socket.IO

### 9.1 إصدار توكن Socket — POST /realtime/token

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/realtime/token" \
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
    "token": "EXAMPLE_SIGNED_SOCKET_TOKEN",
    "expires_at": 1791712920
  }
}
```

صلاحية افتراضية 120 ثانية، وexpires_at بالثواني منذ Unix epoch. هذا اعتماد مختلف عن REST Bearer token ولا يصلح لطلبات HTTP المحمية. يجب أن يكون Socket endpoint مهيأ في إعدادات تطبيقك.

مثال JavaScript باستخدام `socket.io-client` في تطبيقك. الدالتان `scheduleNotificationSync` و`handleApiError` تمثلان دوال عليك تنفيذها في التطبيق؛ الأولى تجدول مزامنة القسم السابق، والثانية تتعامل مع الأخطاء وفق جدولها:

```js
import { io } from 'socket.io-client';

async function connectNotifications({ clientBase, socketOrigin, bearerToken,
                                      scheduleNotificationSync, handleApiError }) {
  const response = await fetch(`${clientBase}/realtime/token`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${bearerToken}`,
      'X-Locale': 'en',
    },
  });
  const payload = await response.json();
  if (!response.ok || !payload.success) {
    handleApiError(response.status, payload);
    return null;
  }
  const socket = io(socketOrigin, {
    transports: ['websocket'],
    auth: { token: payload.data.token },
    reconnection: false,
  });
  socket.on('connect', () => scheduleNotificationSync());
  socket.on('notification.created', () => scheduleNotificationSync());
  socket.on('notification.state_changed', () => scheduleNotificationSync());
  return { socket, expiresAt: payload.data.expires_at };
}
```

في مدير الاتصال الخاص بالتطبيق: عند انقطاع الشبكة أو قرب انتهاء الاعتماد، افصل الاتصال القديم، واطلب توكن Socket جديدًا بتأخير متزايد وحدود محاولات مناسبة، ثم اتصل مجددًا. المثال يعطّل reconnect الآلي لمنع إعادة استخدام اعتماد منتهي. تعامل مع connect_error/disconnect؛ عند 401 من REST ارجع لتدفق الدخول. عند logout أوقف إعادة الاتصال وافصل socket وامسح بيانات الحساب.

السيرفر يضم المستخدم تلقائيًا إلى `private-user.42` في المثال، ولا يحتاج العميل إرسال join. لا يمكنه الاشتراك في غرفة مستخدم آخر.

Payload حدث `notification.created` هو سجل الإشعار نفسه **بدون** غلاف success/data:

```json
{
  "id": "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa",
  "category": "security",
  "action": {
    "screen": "sessions"
  },
  "archived_at": null,
  "revision": 1,
  "title": "Account notification",
  "body": "Welcome! Complete your profile to get started.",
  "read_at": null,
  "created_at": "2026-10-11T10:00:00+00:00"
}
```

Payload حدث `notification.state_changed`:

```json
{
  "version": 3,
  "unread_count": 0
}
```

الأحداث تصل عبر Outbox وQueue وقد تتأخر أو تتكرر. تجاهل إصدارات الحالة الأقدم وادمج السجلات بالـID/revision، واعتبر الحدث إشارة لطلب المزامنة. API هو مرجع العدد والحالة. النشر المجدول يعالج الإشعارات المعلقة كل دقيقة مع زمن إضافي محتمل للطابور.

سحب REST token لا يبطل فورًا اعتماد Socket سبق إصداره؛ يظل حتى انتهاء صلاحيته القصيرة ويفصل السيرفر الاتصال عندها. لذلك افصل اتصال الجهاز محليًا عند الخروج. لا يوجد مسار Client لإنشاء إشعار عشوائي أو إرسال أحداث موثوقة باسم السيرفر.

<a id="closure"></a>
## 10. إغلاق الحساب وفقدان الهاتف

### 10.1 الإغلاق — DELETE /account

```sh
curl --fail-with-body -X DELETE "$CLIENT_BASE/account" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"confirm":true}'
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

اعرض تأكيدًا واضحًا في الواجهة قبل الطلب. يحتاج تحققًا حديثًا؛ نفذ 6.1 و6.2 إذا لزم. عند النجاح تُسحب كل الجلسات وPush وتُقفل المحافظ ويُغلق الحساب بالحذف الناعم. السجلات المالية والتدقيق تبقى محفوظة. الهاتف يظل محجوزًا ولا ينشئ حسابًا جديدًا بمجرد OTP. امسح بيانات التطبيق وافصل Socket.

إذا كان لأي محفظة رصيد غير صفر، أو توجد دفعة pending/processing، أو استرداد معلق، تكون الاستجابة **HTTP 409**:

```json
{
  "success": false,
  "code": "ACCOUNT_CLOSURE_BLOCKED",
  "message": "Resolve your wallet balance and pending payments or refunds before closing your account.",
  "errors": {}
}
```

اعرض سبب التعذر ووجّه المستخدم لتسوية الالتزامات أو الدعم؛ لا تكرر الطلب تلقائيًا.

### 10.2 فقدان الهاتف

اقرأ `data.recovery` من `/auth/requirements`. المسار الحالي `method: "support"` فقط. لا يوجد استرجاع ذاتي بالبريد أو endpoint لتجاوز ملكية الرقم أو لإعادة فتح حساب مغلق. عند فقدان الهاتف القديم استخدم رابط الدعم المهيأ؛ إذا كان null اعرض قناة الدعم التي يحددها المنتج بدل اختراع رابط. تدفق تغيير الهاتف العادي يحتاج الوصول للهاتف الحالي للتحقق الحديث.

<a id="payments"></a>
## 11. ملحق المحفظة والدفع

هذه المسارات جزء من Client API، وتتطلب حسابًا نشطًا. تفاصيل تشغيل المزود وwebhooks في [API.md](API.md). جميع المبالغ أعداد صحيحة بأصغر وحدة للعملة؛ 12500 EGP تعني 125.00 جنيهًا، ولا تقسم كل العملات على 100 لأن بعضها يستخدم ثلاث خانات عشرية.

### 11.1 المحافظ — GET /wallet

```sh
curl --fail-with-body -X GET "$CLIENT_BASE/wallet" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 9,
      "currency": "EGP",
      "balance": 0,
      "is_locked": false
    }
  ]
}
```

### 11.2 الحركات — GET /wallet/{wallet}/transactions

```sh
curl --fail-with-body -X GET "$CLIENT_BASE/wallet/9/transactions?page=1" \
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
    "items": [
      {
        "id": 81,
        "direction": "credit",
        "amount": 12500,
        "balance_after": 12500,
        "reason": "payment_received",
        "created_at": "2026-10-11T11:00:00+00:00"
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

مثال بعد تسوية دفعة؛ قائمة البداية قد تكون فارغة. reason قيمة من سجل الحركة وليست نصًا محليًا. حجم الصفحة 25.

### 11.3 إنشاء دفعة — POST /payments

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/payments" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Idempotency-Key: client42-topup-20261011-001' \
  -H 'Content-Type: application/json' \
  --data '{"amount":12500,"currency":"EGP","provider":"stripe"}'
```

الاستجابة: **HTTP 201**.

```json
{
  "success": true,
  "message": "The payment has been created.",
  "data": {
    "id": 73,
    "uuid": "55555555-5555-4555-8555-555555555555",
    "provider": "stripe",
    "amount": 12500,
    "currency": "EGP",
    "status": "pending",
    "refunded_amount": 0,
    "client_secret": "pi_example_secret_REPLACE_FROM_RESPONSE",
    "created_at": "2026-10-11T10:55:00+00:00"
  }
}
```

المثال يتطلب Stripe sandbox مهيأ. provider اختياري ويأخذ إعداد السيرفر إذا لم يُرسل؛ العملات والمزودون المتاحون مقيدون بإعداد البيئة. amount من 1 حتى الحد المهيأ (99999999 افتراضيًا). استخدم مفتاحًا جديدًا لكل نية دفع جديدة، بطول أقصى 128 ومن الحروف والأرقام و`._:-`.

عند timeout أعد **نفس المفتاح ونفس payload**. لا تنشئ مفتاحًا جديدًا لتجاوز طلب مشكوك في نتيجته. تغيير البيانات مع نفس المفتاح يرجع `409 IDEMPOTENCY_CONFLICT`؛ حالة تحتاج مراجعة قد ترجع `409 RECONCILIATION_REQUIRED`. لا تفترض دعم Idempotency-Key العام على OTP أو كل مسارات الحساب؛ هذا عقد خاص بعمليات الدفع التي تتطلبه.

مع Stripe أكمل الدفع عبر SDK باستخدام client_secret، ومع MyFatoorah استخدم payment_url الذي يرجع للدفعة المعلقة المملوكة لك. لا تعتبر نجاح العودة من شاشة الدفع دليل تسوية. الـWebhook الموثق أو reconciliation هما من يثبتان التسوية ويضيفان الرصيد؛ بعدها أعد جلب الدفعة والمحفظة. لا تسجل client_secret في logs.

### 11.4 قراءة حالة الدفعة — GET /payments/{payment}

```sh
curl --fail-with-body -X GET "$CLIENT_BASE/payments/73" \
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
    "id": 73,
    "uuid": "55555555-5555-4555-8555-555555555555",
    "provider": "stripe",
    "amount": 12500,
    "currency": "EGP",
    "status": "paid",
    "refunded_amount": 0,
    "created_at": "2026-10-11T10:55:00+00:00"
  }
}
```

مثال لدفعة تمت تسويتها. client_secret وpayment_url حقول شرطية وقد تغيب تمامًا؛ لا تفترض وجودها أو كونها null دائمًا.

### 11.5 قائمة الدفعات — GET /payments

```sh
curl --fail-with-body -X GET "$CLIENT_BASE/payments?page=1" \
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
    "items": [
      {
        "id": 73,
        "uuid": "55555555-5555-4555-8555-555555555555",
        "provider": "stripe",
        "amount": 12500,
        "currency": "EGP",
        "status": "paid",
        "refunded_amount": 0,
        "created_at": "2026-10-11T10:55:00+00:00"
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

الدفعات الأحدث أولًا، 25 في الصفحة. لا توفر واجهة العميل الحالية طلب استرداد مباشر؛ تعامل مع الدعم/المسار التشغيلي المعتمد.

<a id="errors"></a>
## 12. الأخطاء وحدود الطلبات

### أمثلة أخطاء

كود OTP غير صحيح — HTTP 422:

```json
{
  "success": false,
  "code": "INVALID_OTP",
  "message": "The verification code is invalid.",
  "errors": {}
}
```

عدم إرسال الاسم أثناء إكمال الحساب — HTTP 422 (رسائل الحقول تتبع اللغة):

```json
{
  "success": false,
  "code": "VALIDATION_FAILED",
  "message": "The submitted data is invalid.",
  "errors": {
    "name": [
      "The name field is required."
    ]
  }
}
```

العملية الحساسة تحتاج تحققًا أحدث — HTTP 403:

```json
{
  "success": false,
  "code": "REAUTHENTICATION_REQUIRED",
  "message": "Verify your phone again before continuing.",
  "errors": {}
}
```

### سلوك التطبيق حسب الخطأ

| HTTP | code | تصرف التطبيق |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | امسح الجلسة غير الصالحة وافصل Socket وابدأ الدخول |
| 401 | `INVALID_CREDENTIALS` | اعرض تعذر الدخول؛ قد يكون الحساب مغلقًا أو غير متاح لهذا النوع |
| 403 | `REAUTHENTICATION_REQUIRED` | OTP للهاتف الحالي ثم إعادة العملية الأصلية |
| 403 | `ACCOUNT_DISABLED`, `ACCOUNT_PENDING`, `FORBIDDEN` | اعرض حالة الحساب/الصلاحية؛ لا تدخل حلقة تسجيل دخول |
| 404 | `NOT_FOUND` | حدّث القائمة؛ المورد غير موجود أو غير مملوك للمستخدم |
| 409 | `PHONE_ALREADY_EXISTS` | اطلب رقمًا آخر أو الدعم |
| 409 | `RESOURCE_CONFLICT` | تحقق من ارتباط FCM بالحساب السابق |
| 409 | `ACCOUNT_CLOSURE_BLOCKED` | تسوية الرصيد والمدفوعات/الاستردادات أولًا |
| 409 | `IDEMPOTENCY_CONFLICT` | أصلح إعادة استخدام المفتاح؛ لا تغيّر بيانات محاولة قائمة |
| 409 | `RECONCILIATION_REQUIRED` | أوقف إعادة الإنشاء وتابع حالة العملية عبر الدعم/الخادم |
| 422 | `VALIDATION_FAILED` | اعرض errors بجوار الحقول |
| 422 | `INVALID_OTP` | اسمح بإعادة إدخال الكود ضمن حدود المحاولات |
| 422 | `OTP_EXPIRED`, `OTP_CONSUMED` | ابدأ challenge جديدًا عند السماح بالإرسال |
| 422 | `INVALID_PHONE_NUMBER`, `PHONE_COUNTRY_MISMATCH`, `UNSUPPORTED_PHONE_COUNTRY`, `PHONE_TYPE_NOT_ALLOWED` | صحح الدولة/رقم الموبايل |
| 422 | `PHONE_NOT_VERIFIED` | الحساب يحتاج هاتفًا موثقًا قبل العملية؛ اعرض مسار الدعم إذا تعذر التصحيح |
| 429 | `OTP_COOLDOWN` | انتظر مؤقت إعادة الإرسال |
| 429 | `OTP_ATTEMPTS_EXCEEDED`, `RATE_LIMITED` | أوقف التكرار وانتظر السماح بالمحاولة |
| 503 | `PROVIDER_UNAVAILABLE` | اعرض تعذر الخدمة مؤقتًا مع retry محدود |
| 500 | `INTERNAL_ERROR` | احتفظ بـX-Request-ID للدعم بدون تسريب التوكن |

### القيم الافتراضية

| الحد | القيمة |
| --- | --- |
| صلاحية OTP | 300 ثانية |
| إعادة الإرسال | بعد 60 ثانية |
| محاولات التحقق للكود | 5 |
| الإرسال لكل هاتف عبر الأغراض والأنواع | 6 في الساعة |
| HTTP طلب OTP | 3 في الدقيقة لكل IP |
| HTTP تحقق OTP | 5 في الدقيقة لكل IP |
| API المحمي العام | 60 في الدقيقة لكل مستخدم |
| إصدار Socket/تسجيل جهاز/رفع صورة/إنشاء دفعة/إغلاق حساب | 5 في الدقيقة لكل IP عبر limiter مشترك |
| صلاحية REST token | 1440 دقيقة |
| نافذة التحقق الحديث | 5 دقائق |
| الجلسات | 10 |
| صلاحية Socket token | 120 ثانية |

حدود الطلبات قد تتراكم: حد الـIP وحد الهاتف وحد المجموعة المحمية يمكن أن يطبقوا معًا. هذه قيم الإعدادات الافتراضية وليست ضمانًا ثابتًا لكل نشر. احترم Retry-After إذا ظهر في 429؛ لا تفترض وجوده في كل خطأ OTP. لا تستخدم الطلبات المرفوضة أثناء cooldown كوسيلة polling؛ قد تؤثر على حصة المحاولات.

عند فقدان استجابة تحقق OTP قد يكون الكود استُهلك بالفعل. لا تعِد المحاولة بلا حدود، ولا تفترض أن نفس challenge سيعيد توكن الدخول السابق. إذا لم تحصل على التوكن، ابدأ دخولًا جديدًا بعد السماح بالإرسال.

<a id="integration"></a>
## 13. ترتيب التنفيذ في التطبيق

1. **بداية التطبيق:** اقرأ المتطلبات والدول. إذا عندك توكن محفوظ، جرّب GET profile؛ 401 يعيد للدخول. إذا profile_completed=false اعرض إكمال الحساب/الشروط.
2. **الدخول:** اطلب OTP ثم تحقق، واحفظ التوكن وexpires_at. اتبع next_step. المستخدم لا يُسأل عن بريد أو كلمة مرور.
3. **بعد الدخول:** اجلب التفضيلات، وسجل FCM إذا وافق المستخدم، وحمّل الصندوق ثم فعّل المزامنة وSocket. فشل Push لا يلغي نجاح الدخول.
4. **الواجهة الرئيسية:** اعرض unread_count المرجعي. حدّث السجلات بالـID/revision. عند الرجوع من الخلفية نفذ مزامنة لتغطية انقطاع Socket.
5. **الإعدادات:** الملف والصورة والجلسات وتفضيلات Push. رقم الهاتف عبر تدفق التحقق المنفصل فقط.
6. **العمليات الحساسة:** تعامل مع REAUTHENTICATION_REQUIRED، ثم أعد العملية. بعد تغيير الرقم امسح التوكن وسجل بالرقم الجديد.
7. **الخروج/الإغلاق:** نفذ طلب الخادم، ثم افصل Socket وأوقف retries واحذف التوكن والبيانات المحلية الخاصة بالحساب. لا تشارك cache الإشعارات بين مستخدمين.

### سيناريو قبول يدوي للمطور

- رقم جديد → OTP → complete_profile → الموافقة على الإصدار الحالي → home.
- رقم موجود بملف مكتمل → OTP → home، مع ظهور جلسة وإشعار دخول جديد.
- device_id نفسه عند الدخول مجددًا → الجلسة السابقة غير صالحة؛ أعد تسجيل Push.
- كود خاطئ/منتهي أو إعادة إرسال سريعة → معالجة code المناسبة بدون حلقات طلبات.
- قراءة/أرشفة على جهاز A → مزامنة جهاز B تعكس الحالة والعدد.
- read-all مع إشعار يصل بعد snapshot → الإشعار الجديد يظل غير مقروء.
- سحب جلسة أخرى → REST الخاص بها يُرفض ويُلغى اشتراك Push التابع لها.
- تغيير الهاتف → كل الجلسات مسحوبة، والدخول بالرقم الجديد يعمل.
- رصيد غير صفر → الإغلاق يرجع ACCOUNT_CLOSURE_BLOCKED.
- شروط بإصدار أحدث → اعرض الموافقة مجددًا بدل تجاوز complete_profile.

## مصادر العقد داخل المشروع

- المسارات: [routes/client/api.php](routes/client/api.php)، [routes/shared/account.php](routes/shared/account.php)، [routes/shared/payments.php](routes/shared/payments.php).
- شكل المستخدم: [UserResource.php](app/Http/Resources/Shared/UserResource.php).
- الإشعارات والمزامنة: [NotificationController.php](app/Http/Controllers/Shared/NotificationController.php)، [UserNotification.php](app/Models/UserNotification.php).
- الاستجابات والأكواد: [ApiResponse.php](app/Support/ApiResponse.php)، [ErrorCode.php](app/Enums/ErrorCode.php).
- الإعدادات: [client.php](config/client.php)، [otp.php](config/otp.php)، [realtime.php](config/realtime.php).
- المرجع العام وتكامل المزودين: [API.md](API.md). المرجع التفاعلي `/docs/api` والـOpenAPI `/docs/api.json` يتبعان صلاحيات البيئة الموضحة هناك.
