# روابط المشاركة وفتح التطبيق

[الفهرس](README.md) · [إحالات العميل](../Client/referrals.md)

## النطاق المنفذ

رابط HTTPS واحد يعرض محتوى عامًا أو يفتحه داخل التطبيق عند إعداد App Links / Universal Links في نسخة الموبايل. Laravel يوفّر إنشاء الرابط وتفسيره وصفحة fallback وملفات التحقق. لا يوجد كود موبايل في هذا المستودع، وبالتالي يلزم تنفيذ استقبال الروابط في تطبيق Android وiOS قبل إطلاق الميزة.

الوجهات الحالية: `home`، `page` منشورة، و`category` مرئية بكل أسلافها. الرابط ليس وسيلة تسجيل دخول ولا يمنح صلاحية أو نقاطًا. لا توجد روابط منتجات أو طلبات أو دعم خاص ضمن المشاركة العامة.

## تفسير الرابط داخل التطبيق

```http
GET /api/v1/links/0123456789abcdef0123456789abcdef
X-Locale: ar
Accept: application/json
```

هذا الكود وكل الدومينات التالية أمثلة توضيحية؛ استخدم القيم الفعلية الراجعة من إنشاء الرابط.

```sh
curl "$API_BASE/links/0123456789abcdef0123456789abcdef" -H 'X-Locale: ar'
```

HTTP 200:

```json
{
  "success": true,
  "message": null,
  "data": {
    "code": "0123456789abcdef0123456789abcdef",
    "url": "https://links.example.com/l/0123456789abcdef0123456789abcdef",
    "target": {"type": "page", "id": null, "slug": "about-us"},
    "requires_auth": false,
    "title": "من نحن",
    "description": "تعرف على خدماتنا.",
    "web_url": "https://example.com/pages/about-us?locale=ar",
    "referral_code": "A1B2C3D4E5F60718",
    "stores": {
      "android": "https://play.google.com/store/apps/details?id=com.example.app&referrer=link_code%3D0123456789abcdef0123456789abcdef",
      "ios": "https://apps.apple.com/app/id123456789"
    }
  }
}
```

`referral_code` يكون `null` عند المشاركة بدون دعوة. `web_url` متاح للصفحات فقط؛ `home` و`category` لهما `null`. رابط المتجر غير المضبوط أو غير الصحيح يرجع `null`. عدم توفر المتجر لا يمنع تفسير الوجهة.

الاستجابة `Cache-Control: no-store, private`. يعاد التحقق من نشر المحتوى وظهور التصنيف وحالة صاحب الرابط كل مرة. الرابط الملغى أو صاحب الحساب المغلق/غير النشط أو المحتوى المخفي يرجع `404 NOT_FOUND`. لغة غير مدعومة ترجع `422 UNSUPPORTED_LOCALE`، وتجاوز الحد العام `429`.

## رحلة الموبايل

1. سجّل استقبال `https://LINKS_HOST/l/*` في Android وiOS. تعامل مع فتح التطبيق من الإغلاق ومع وصول رابط أثناء تشغيله.
2. اقبل المضيف المعتمد والمسار فقط، واستخرج كودًا من 32 حرفًا hexadecimal صغيرًا.
3. اطلب `/links/{code}`؛ لا تعتمد على target أو referral_code يضيفهما أحد يدويًا إلى query string.
4. خزّن الوجهة وكود الدعوة مؤقتًا في الجهاز حتى تنتهي رحلة التسجيل. أزل بيانات الرحلة عند الإلغاء أو اكتمالها أو تبديل الحساب.
5. افتح الصفحة أو التصنيف باستخدام endpoints المحتوى الحالية. القيم `type` محصورة؛ نوع غير معروف في نسخة التطبيق يعرض الرئيسية بدل تنفيذ مسار عشوائي.
6. عند OTP ثم إكمال الملف، أرسل كود الدعوة حسب [دليل الإحالات](../Client/referrals.md). وصول الرابط وحده لا يربط الإحالة.
7. لو الكود غير صالح يستطيع المستخدم المتابعة بدون إحالة. حساب قائم يفتح المحتوى فقط.

لا تستهلك رابط الدعوة عند أول فتح؛ يمكن مشاركته مع أشخاص متعددين. التسجيل من كود يدوي لا يثبت أن هناك ضغطة أو تثبيتًا سابقًا، ولا يُمنح عليه أي مكافأة.

## التطبيق غير مثبت

`GET /l/{code}` يعرض صفحة عربية/إنجليزية متجاوبة، مع عنوان ووصف Open Graph للمعاينات، زر فتح التطبيق، كود الدعوة، ورابطَي المتجر عند ضبطهما. `?locale=ar` و`X-Locale` مدعومان. الصفحة العامة المنشورة لها زر قراءة على الويب.

لا نحاول استنتاج وجود التطبيق من User-Agent، ولا نعيد التوجيه للمتجر تلقائيًا. المستخدم يختار المتجر؛ المتصفح الداخلي قد يحتاج فتح الرابط في متصفح خارجي. زر فتح التطبيق يخضع لسلوك النظام والمتصفح واختيارات المستخدم، وليس ضمانًا لإجبار التطبيق على الفتح. الرابط المنتهي/المحذوف يعرض صفحة 404 واضحة.

**Android:** رابط Google Play يحمل `referrer` يحتوي `link_code`. بعد التثبيت من Play يقرأ الموبايل Install Referrer مرة في رحلة التثبيت ويطلب `/links/{code}` لاستعادة الوجهة وكود الدعوة. احتفظ بالقيم أثناء OTP. هذه قيمة عامة لتحديد المحتوى وليست توكن مصادقة أو دليلًا على استحقاق نقاط. إذا لم تتوفر البيانات، اطلب إعادة فتح الرابط أو إدخال كود الدعوة. [Google Play Install Referrer](https://developer.android.com/google/play/installreferrer).

**iOS:** التطبيق المثبت يستخدم Universal Links. استعادة وجهة قبل التثبيت تلقائيًا غير منفذة. المستخدم يعيد فتح الرابط بعد التحميل، أو يدخل كود الدعوة أثناء التسجيل. إدخال كود الدعوة يستعيد الإحالة فقط وليس وجهة المحتوى. لا نستخدم fingerprinting أو قراءة clipboard تلقائيًا أو Firebase Dynamic Links. [Apple Universal Links](https://developer.apple.com/documentation/xcode/supporting-universal-links-in-your-app).

لا توجد إحصائيات تثبيت أو مكافآت بناءً على فتح الرابط، ولا QR مولّد في هذه المرحلة.

## إعداد البيئة والنشر

الإعدادات في `config/links.php`، ومفاتيحها في `.env.example`:

```dotenv
LINKS_BASE_URL=https://links.example.com
LINKS_ANDROID_PACKAGE=com.example.app
LINKS_ANDROID_FINGERPRINTS=
LINKS_IOS_APP_ID=ABCDEFGHIJ.com.example.app
LINKS_ANDROID_STORE_URL=https://play.google.com/store/apps/details?id=com.example.app
LINKS_IOS_STORE_URL=https://apps.apple.com/app/id123456789
```

القيم أمثلة وليست بيانات تطبيق منشور. املأ بصمة SHA-256 الحقيقية من Play App Signing، ومعرف تطبيق Apple الحقيقي. يمكن فصل أكثر من بصمة بفاصلة. `LINKS_BASE_URL` هو origin فقط بدون query أو مسار، ويجب أن يكون HTTPS في النشر. عند تركه فارغًا يستخدم `APP_URL`. روابط المتاجر مقيدة بـHTTPS على `play.google.com/store/apps/details?id=...` و`apps.apple.com/.../id...`.

في cPanel وجّه دومين الروابط إلى `public` الخاص بنفس إصدار Laravel. استخدم شهادة TLS صالحة. لا توجّه دومين الروابط إلى خدمة Socket.IO. شغّل migrations وحدّث config/route cache وفق [دليل النشر](../Operations/deployment.md).

ملفا التحقق يرجعان JSON الخاص بالمنصة، بدون غلاف API:

```http
GET /.well-known/assetlinks.json
GET /.well-known/apple-app-site-association
```

يجب وصولهما مباشرة بـ200 و`application/json` بدون تسجيل دخول أو redirect. إذا كانت معرفات المنصة أو بصماتها ناقصة/غير صحيحة يرجع الملف الخاص بها 404 بدل نشر بيانات وهمية. الكاش 300 ثانية. تأكد أن إعدادات الاستضافة لا تمنع `.well-known` وأن أي ملفات ثابتة قديمة لا تتقدم على Laravel routes.

Android يحتاج manifest intent filter بنطاق HTTPS والمضيف الصحيح ومسار `/l/` مع التحقق التلقائي. iOS يحتاج Associated Domains لنفس المضيف، وأن يحتوي التطبيق على معالج Universal Links. ملف Apple هنا يعلن `/l/*` فقط. استخدم دومين/معرفات منفصلة لنسخة الاختبار إذا كانت مختلفة عن الإصدار المنشور.

## التحقق قبل الإطلاق

- التطبيق مثبت وغير مثبت، مفتوح ومغلق، ودخول جديد وحساب قائم.
- روابط من WhatsApp والمتصفح العادي، وكلتا اللغتين.
- رابط ملغى وصفحة أُرشفت وتصنيف مخفي وحساب مالك أُغلق.
- استعادة Install Referrer على جهاز Android بتثبيت حقيقي من Play.
- إعادة فتح الرابط بعد التثبيت على iOS، والكود اليدوي كبديل.
- ملفات association وشهادة التوقيع الفعلية؛ نجاح اختبارات PHP لا يثبت تفعيل الربط في نسخة الموبايل.
