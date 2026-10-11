# إحالات العميل وروابط المشاركة

[فهرس العميل](README.md) · [الروابط وتكامل الموبايل](../Shared/deep-links.md) · [تجهيز cURL](../README.md)

## السياسة الحالية

الميزة تسجل الإحالة فقط. **لا توجد نقاط أو مكافآت أو تحويل للمحفظة أو وعود بمكافآت مستقبلية**. كل إحالة حالتها `registered`، و`rewards_enabled=false`. قرار مكافأة الإحالات السابقة يحتاج سياسة منفصلة لاحقًا.

صاحب الدعوة يجب أن يكون عميلًا نشطًا، موثق الهاتف، ومكمل الملف وقابلًا للشروط الحالية عند إنشاء الرابط. المدعو حساب عميل جديد، موثق بالـOTP، ولم يُكمل ملفه لأول مرة. نافذة الربط 24 ساعة من إنشاء الحساب، مضبوطة في `links.referral_claim_hours`. إعادة الدخول أو التثبيت لا تعيد فتحها، والحسابات الموجودة قبل إطلاق الميزة لا تحصل على نافذة تلقائيًا. لا تُربط إحالة للنفس.

كل المسارات هنا تتطلب `Authorization: Bearer CLIENT_TOKEN` وعميلًا نشطًا. مستخدم Admin أو Vendor لا يستخدمها. التاريخ لا يكشف اسم أو هاتف أو معرف حساب المدعو.

## ملخص الإحالات

```sh
curl "$CLIENT_BASE/referrals" -H "Authorization: Bearer $CLIENT_TOKEN" -H 'X-Locale: ar'
```

`GET /api/v1/client/referrals` لا ينشئ بيانات. مثال لحساب بدأ التسجيل للتو ولم يرسل دعوات:

```json
{
  "success": true,
  "message": null,
  "data": {
    "code": null,
    "share_url": null,
    "registered_count": 0,
    "rewards_enabled": false,
    "has_claimed": false,
    "can_claim": true,
    "claim_expires_at": "2026-10-12T12:00:00+00:00"
  }
}
```

`can_claim` هو المرجع لعرض حقل الدعوة. لا تعرض شاشة نقاط أو قيمة مكافأة. `claim_expires_at` قد يكون `null` للحسابات القديمة، و`share_url` قد يكون `null` حتى مع وجود كود إذا لم يُنشأ رابط دعوة للرئيسية أو أُلغي.

## إنشاء رابط دعوة ثابت

```sh
curl -X POST "$CLIENT_BASE/referrals/link" \
  -H "Authorization: Bearer $CLIENT_TOKEN" -H 'Accept: application/json'
```

`POST /api/v1/client/referrals/link` لا يحتاج body. يرجع HTTP 200 وبيانات `ShareLinkResource` كاملة كما في [دليل الروابط](../Shared/deep-links.md)، بوجهة:

```json
{"type":"home","id":null,"slug":null}
```

احتفظ بـ`data.url` للمشاركة و`data.referral_code` للكود اليدوي. تكرار الطلب يرجع رابط الدعوة النشط نفسه. بعد إلغائه، الطلب التالي ينشئ رابطًا جديدًا بنفس كود دعوة الحساب.

## مشاركة محتوى

```sh
curl -X POST "$CLIENT_BASE/share-links" \
  -H "Authorization: Bearer $CLIENT_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"target":{"type":"page","slug":"about-us"},"include_referral":true}'
```

`POST /api/v1/client/share-links` يرجع HTTP 201 بنفس [الاستجابة الكاملة](../Shared/deep-links.md). الوجهات المقبولة:

```json
{"target":{"type":"home"}}
```

```json
{"target":{"type":"category","id":12},"include_referral":false}
```

`include_referral` افتراضيًا `true`. عند `false` لا يُرفق كود دعوة. كل طلب مشاركة محتوى ينشئ رابطًا جديدًا؛ احتفظ بالرابط الناتج عند إعادة فتح نافذة المشاركة. لا تدعم هذه العملية `Idempotency-Key`، ولا تنفذ أثرًا ماليًا. إنشاء الروابط محدود بـ10 طلبات/دقيقة للحساب، مشترك مع إنشاء رابط الدعوة.

السيرفر يرفض `redirect_url` و`url` و`user_id` و`referral_code` كمدخلات إنشاء، ويتحقق من نوع الوجهة ومفاتيحها. `page` تحتاج slug منشورًا، و`category` تحتاج id مرئيًا. id المثال يجب استبداله بمعرف فعلي.

## ربط الإحالة أثناء التسجيل

بعد OTP وصدور توكن العميل، وقبل إكمال الملف، اختر أحد المسارين:

**طلب مستقل:**

```sh
curl -X POST "$CLIENT_BASE/referrals/claim" \
  -H "Authorization: Bearer $CLIENT_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"referral_code":"A1B2C3D4E5F60718"}'
```

HTTP 200، مثال:

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 42,
    "status": "registered",
    "created_at": "2026-10-11T12:00:00+00:00"
  }
}
```

أو أضف `referral_code` الاختياري إلى طلب إكمال الملف الموجود:

```json
{
  "name": "أحمد محمد",
  "terms_version": "1",
  "terms_accepted": true,
  "referral_code": "A1B2C3D4E5F60718"
}
```

يُرسل إلى `POST /api/v1/client/auth/complete-profile`، ويرجع استجابة الملف المعتادة. استخدم نسخة الشروط الفعلية، وأضف `terms_revision_id` عندما تكون هناك شروط منشورة. ربط الدعوة وحفظ الملف والشروط يتم داخل transaction واحدة. كود غير صالح يرجع 422 ولا يُكمل الملف؛ اعرض الخطأ مع خيار حذف الكود والمتابعة بدون إحالة.

أول ربط صالح ثابت للحساب. تكرار الكود نفسه يرجع نفس سجل الإحالة حتى بعد إكمال الملف/انتهاء النافذة، وتبديله بكود آخر يرجع `409 RESOURCE_CONFLICT`. لا يحتاج claim إلى `Idempotency-Key` منفصل؛ القيد الفريد للحساب يمنع تكرار الربط. الكود 16 حرفًا hexadecimal ويقبل الأحرف الصغيرة والكبيرة. لا تُرسل بيانات صاحب الدعوة أو قيمة نقاط.

## سجل الدعوات

```sh
curl "$CLIENT_BASE/referrals/history?per_page=25" -H "Authorization: Bearer $CLIENT_TOKEN"
```

`GET /api/v1/client/referrals/history`، HTTP 200:

```json
{
  "success": true,
  "message": null,
  "data": {
    "items": [{"id":42,"status":"registered","created_at":"2026-10-11T12:00:00+00:00"}],
    "meta": {"current_page":1,"last_page":1,"per_page":25,"total":1}
  }
}
```

`per_page` من 1 إلى 100. الترتيب الأحدث أولًا. `registered_count` والتاريخ يعكسان تسجيل الإحالة فقط، وليس تنزيلًا مثبتًا أو استحقاقًا لمكافأة.

## إلغاء رابط

```sh
curl -X DELETE "$CLIENT_BASE/share-links/0123456789abcdef0123456789abcdef" \
  -H "Authorization: Bearer $CLIENT_TOKEN"
```

`DELETE /api/v1/client/share-links/{code}` يرجع HTTP 200:

```json
{"success":true,"message":null,"data":null}
```

العملية خاصة بصاحب الرابط وقابلة للتكرار. بعدها يرجع تفسير الرابط 404. لا تُلغى الإحالات السابقة أو كود الدعوة الثابت؛ من احتفظ بالكود يستطيع استخدامه إذا ظل مؤهلًا. إغلاق أو تعطيل حساب صاحب الدعوة يمنع ربط إحالات جديدة له.

## الأخطاء

| HTTP | المعنى |
|---|---|
| 401 | يحتاج تسجيل دخول |
| 403 | نوع حساب غير صحيح، حساب غير نشط، هاتف غير موثق للـclaim، أو ملف غير مكتمل لإنشاء الروابط |
| 404 | محتوى غير متاح، رابط ملغى، أو محاولة إلغاء رابط لا يملكه المستخدم |
| 409 | الحساب مرتبط بإحالة مختلفة |
| 422 | مدخلات غير صحيحة، كود غير متاح، أو حساب غير مؤهل/نافذة منتهية |
| 429 | تجاوز حد الطلبات؛ claim المستقل محدود بـ5 طلبات/دقيقة والحساب خاضع للحد العام أيضًا |

`referral.claimed` و`share_link.revoked` مسجلان في سجل التدقيق. لا يرسل الربط إشعار مكافأة ولا يغير المحفظة.
