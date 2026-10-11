# رحلة العميل كاملة

[فهرس العميل](README.md) · [تجهيز المتغيرات](../README.md) · [عقد الأخطاء](../Shared/http-contract.md)

## 1. فتح التطبيق

اقرأ `GET /api/v1/client/auth/requirements` و`GET /api/v1/locations/countries`. اعرض الشروط باستخدام الرابط/الإصدار الراجعين؛ الصفحات العامة عبر `GET /api/v1/pages/{slug}`. إذا كان لديك توكن محفوظ، جرّب `GET /api/v1/client/profile`: النجاح يستعيد الحساب، و 401 ينتقل للدخول. لا يوجد طلب refresh.

## 2. التسجيل أو الدخول بالهاتف

التسجيل والدخول نفس التسلسل، دون حقل email. اختر country_id من استجابة الدول ثم أرسل:

```http
POST /api/v1/client/auth/otp/request
Content-Type: application/json
X-Locale: en
```

```json
{"country_id":1,"phone":"+201012345678"}
```

HTTP 200:

```json
{"success":true,"message":"A verification code has been sent.","data":{"challenge_id":"11111111-1111-4111-8111-111111111111","expires_in":300,"resend_after":60}}
```

احفظ challenge وشغّل المؤقت. الكود يصل SMS ولا يرجع من API. استخدم code كنص من ستة أرقام. أرسل `POST /api/v1/client/auth/otp/verify`:

```json
{"country_id":1,"phone":"+201012345678","challenge_id":"11111111-1111-4111-8111-111111111111","code":"123456","device_name":"Ahmed iPhone","device_id":"22222222-2222-4222-8222-222222222222"}
```

الرد الكامل في [الدخول والتسجيل](authentication.md). يحتوي `data.user` و`data.token` و`data.expires_at` و`data.next_step`. احفظ التوكن بأمان واستخدمه في Authorization. أعد استخدام device_id لنفس تثبيت التطبيق؛ إعادة الدخول تسحب الجلسة السابقة لذلك التثبيت. OTP ليس عملية قابلة لإعادة الرد القديم؛ إذا فُقد الرد واعتُبر الكود مستهلكًا، ابدأ دخولًا جديدًا عند السماح بالإرسال.

## 3. إكمال الملف وقبول الشروط

عندما `next_step=complete_profile` أو `profile_completed=false`، اطلب الاسم واعرض الشروط قبل إرسال `POST /api/v1/client/auth/complete-profile` بالتوكن. مثال عندما نشر الأدمن إصدارًا برقم revision=12 (استبدله بالقيم الفعلية):

```json
{"name":"Ahmed Ali","locale":"ar","timezone":"Africa/Cairo","terms_accepted":true,"terms_version":"terms-1-2","terms_revision_id":12}
```

إذا لم توجد شروط منشورة، استخدم `terms_version` من requirements ولا تختلق revision. نجاح الطلب يعيد UserResource بالاسم واللغة و`profile_completed=true` وإصدار الشروط المقبول. عند تغير النسخة أثناء القراءة، أعد المتطلبات واعرض النص الأحدث قبل إعادة القبول. `next_step` توجيه للواجهة؛ ليس middleware يمنع تلقائيًا كل الميزات لحين الإكمال.

## 4. تجهيز الشاشة الرئيسية

اقرأ الملف والمحافظ وصندوق الإشعارات؛ يمكن تنفيذ القراءات المستقلة معًا:

| الغرض | Endpoint | الدليل |
|---|---|---|
| بيانات الحساب | `GET /client/profile` | [الملف والصورة](profile.md) |
| الأرصدة | `GET /client/wallet` | [المحفظة](wallet.md) |
| الإشعارات | `GET /client/notifications` | [الإشعارات والمزامنة](notifications.md) |
| إعدادات Push | `GET /client/notification-preferences` | [الأجهزة والتفضيلات](push-notifications.md) |

الجدول يختصر `/api/v1`. عند الحصول على FCM token، سجله بـ`POST /client/devices`؛ device_id الخاص بـOTP ليس FCM token. أصدر اعتماد Socket عبر `POST /client/realtime/token` ثم استمع للأحداث. استخدم REST للمزامنة بعد كل إعادة اتصال، وادمج الإشعارات بالـid وrevision. [مثال JavaScript](../Shared/realtime-protocol.md).

## 5. تعديل الحساب والصورة

استخدم `PATCH /client/profile` للاسم واللغة والمنطقة الزمنية فقط. الصورة عبر `POST /client/profile/avatar` multipart بالحقل avatar. احفظ avatar_url الراجع؛ ينتهي بعد خمس دقائق ويمكن تجديده بقراءة profile. تغيير الهاتف له تحقق مستقل، ولا يُرسل ضمن PATCH.

## 6. الدفع والمحفظة

أنشئ `POST /client/payments` مثل `{"provider":"stripe","currency":"EGP","amount":1500}` مع Idempotency-Key جديد لهذه الدفعة. 1500 هنا تعني 15.00 EGP. احفظ id والمفتاح محليًا قبل الاتصال. استخدم client_secret مع Stripe أو payment_url مع MyFatoorah.

بعد العودة من الدفع اقرأ `GET /client/payments/{payment}` ثم wallet؛ لا تضف الرصيد في الواجهة اعتمادًا على redirect. التسوية تأتي من المزود. إذا ضاعت الاستجابة، أعد **نفس الطلب والمفتاح**؛ 409 RECONCILIATION_REQUIRED يعني متابعة الحالة مع الدعم، دون إنشاء دفعة بديلة. [أمثلة الطلب والرد](payments.md).

## 7. التواصل والدعم مع صورة

للزائر: اقرأ contact-settings ثم أرسل نموذج contact-messages مع request_id واحفظ reference. لا يوجد رابط عام لقراءة الرسالة؛ الرد التفاعلي يحتاج حسابًا ومحادثة دعم.

للعميل المسجل: ارفع صورة اختيارية عبر `POST /client/media` بالحقلين purpose=support وfile. احفظ data.id ثم أنشئ `POST /client/support/conversations`:

```json
{"request_id":"33333333-3333-4333-8333-333333333333","subject":"Payment support","client_message_id":"44444444-4444-4444-8444-444444444444","body":"I paid but the balance has not updated.","media_ids":["019a0123-4567-7890-abcd-012345678901"]}
```

استبدل media ID بنتيجة الرفع. الطلب يعيد تفاصيل المحادثة؛ اقرأ messages بعد ذلك. عند إشعار دعم، اجلب الرسائل باستخدام after_id وتابع has_more. علّم المقروء من خلال through_message_id بعد العرض. اسمح بإعادة إرسال نفس client_message_id والنص والمرفقات عند timeout. [أمثلة الردود وحالات المحادثة](support-chat.md) · [التحميل والتنزيل](media.md).

## 8. إدارة الوصول والخروج

اعرض [الجلسات](sessions.md) وأتح سحب جهاز محدد. لتغيير الهاتف: تحقق حديث للهاتف الحالي إن لزم ثم اطلب OTP للرقم الجديد وتحقق منه؛ النجاح يسحب جميع الجلسات ويتطلب الدخول بالرقم الجديد. [التسلسل الكامل](phone-change.md).

عند logout احذف بيانات الجلسة وافصل Socket وأوقف reconnect. عند إغلاق الحساب، نفّذ التحقق الحديث وسوِّ الأرصدة والعمليات المعلقة ثم أرسل confirm=true إلى مسار [إغلاق الحساب](account-closure.md). فقدان الهاتف يوجَّه للدعم وفق requirements، دون استعادة عبر Email.

## التعامل مع انقطاع الشبكة

احتفظ بمفاتيح العمليات المالية ومعرفات رسائل الدعم قبل إرسالها. احتفظ بمؤشر مزامنة الإشعارات بعد اكتمال كل صفحات snapshot فقط. لا تعاود 401 في حلقة؛ احترم مؤقتات OTP و 429، واعرض errors للحقول في 422. تفاصيل 409 تختلف حسب الميزة: نسخة محتوى قديمة، مفتاح تكرار مختلف البيانات، أو تسوية مالية غير محسومة.
