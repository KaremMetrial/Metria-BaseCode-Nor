# إعدادات بدء تشغيل التطبيق

[الفهرس](README.md) · [رحلة العميل](../Client/full-flow.md) · [عقد HTTP](http-contract.md)

## الطلب

`GET /api/v1/config` متاح بدون تسجيل دخول. يقرأ القيم الموجودة في المشروع فقط: اللغات، متطلبات تسجيل العميل، توقيت OTP، بيانات التواصل، وحدود رفع الملفات. لا توجد مفاتيح لإصدارات الموبايل أو الصيانة أو feature flags.

بعد [تجهيز API_BASE](../README.md):

```sh
curl -i "$API_BASE/config" \
  -H 'Accept: application/json' \
  -H 'X-Locale: ar'
```

HTTP 200، مع `Content-Language: ar` و`Cache-Control: no-cache, private` و`ETag`.

مثال توضيحي باستخدام الحدود الافتراضية، قبل نشر الشروط أو تعبئة بيانات التواصل، ومع عدم إعداد روابط بديلة:

```json
{
  "success": true,
  "message": null,
  "data": {
    "localization": {
      "default": "en",
      "fallback": "en",
      "current": "ar",
      "header": "X-Locale",
      "supported": [
        {"code": "en", "name": "English", "native_name": "English", "rtl": false},
        {"code": "ar", "name": "Arabic", "native_name": "العربية", "rtl": true}
      ]
    },
    "client_auth": {
      "requirements": {
        "terms_version": "1",
        "terms_revision_id": null,
        "terms_url": null,
        "recovery": {"method": "support", "support_url": null}
      },
      "otp": {"expires_in_seconds": 300, "resend_after_seconds": 60}
    },
    "contact": {
      "phone": null,
      "whatsapp": null,
      "social_links": [],
      "address": null,
      "working_hours": null,
      "intro": null
    },
    "uploads": {
      "avatar": {
        "max_size_bytes": 2097152,
        "extensions": ["jpg", "jpeg", "png", "webp"],
        "image_max_width": 2048,
        "image_max_height": 2048
      },
      "support": {
        "max_size_bytes": 10485760,
        "extensions": ["jpg", "jpeg", "png", "webp", "pdf"],
        "image_max_width": 4096,
        "image_max_height": 4096
      },
      "page": {
        "max_size_bytes": 5242880,
        "extensions": ["jpg", "jpeg", "png", "webp"],
        "image_max_width": 4096,
        "image_max_height": 4096
      }
    }
  }
}
```

## معنى البيانات ومصادرها

| القسم | المصدر والاستخدام |
|---|---|
| `localization` | `config/languages.php`. اعرض قائمة اللغات واستخدم `rtl` للاتجاه. `current` هي اللغة التي اختارها middleware لهذا الطلب. |
| `client_auth.requirements` | نفس خدمة `GET /client/auth/requirements` وبنفس الحقول، بما فيها الشروط المنشورة واستعادة الحساب عن طريق الدعم. |
| `client_auth.otp` | `config/otp.php`. قيم إرشادية؛ استجابة طلب الكود (`expires_in` و`resend_after`) هي المرجع الفعلي للمؤقت. |
| `contact` | إعدادات التواصل التي يعدلها الأدمن، مترجمة إلى لغة الطلب. روابط السوشيال عناصر `{label, url}`. القيم غير المتوفرة `null`، والقائمة الفارغة `[]`. |
| `uploads` | `config/media.php`؛ نفس القيم المستخدمة في Form Requests. `avatar` للملف الشخصي، `support` للمرفقات، و`page` لميديا الصفحات الخاصة بالأدمن. |

أحجام الملفات بالبايت، وأبعاد الصور بالبكسل. قيود الأبعاد لا تنطبق على PDF. الامتداد المسموح لا يلغي فحص المحتوى والصلاحيات والحصة التخزينية. صور الميديا تحتاج GD، وPDF يحتاج ClamAV؛ قائمة الأنواع ليست فحصًا لحالة خدمات السيرفر. راجع [تخزين الميديا](media-storage.md).

لا يضيف هذا endpoint صلاحيات رفع لأي حساب. لا يعيد مسارات التخزين أو بيانات الخدمات أو مفاتيح Socket/SMS/الدفع. بيانات المستخدم والإشعارات وتوكن Socket لها طلباتها المحمية.

## تدفق الاستخدام والكاش

1. اطلب الإعدادات عند تشغيل التطبيق مع `X-Locale` صريحًا. احفظ الاستجابة و`ETag` لكل لغة وبيئة API.
2. استخدم `supported` لاختيار اللغة، ومتطلبات التسجيل لعرض الشروط، وحدود الرفع للتحقق المبدئي.
3. اطلب الدول من `GET /locations/countries` عند اختيار رقم الهاتف، والصفحات من `GET /pages` عند عرض روابطها.
4. في الطلب التالي أرسل `If-None-Match` بالقيمة الكاملة الراجعة في `ETag`، بما فيها علامتا الاقتباس.
5. عند `304` لا تحاول قراءة JSON؛ استخدم الاستجابة المحفوظة. عند `200` استبدلها واحفظ `ETag` الجديد.
6. عند تغيير اللغة أعد الطلب. عند انقطاع الشبكة يمكن عرض النسخة المحفوظة، لكن التسجيل والرفع يظلان خاضعين لتحقق السيرفر بالقيم الحالية.

```sh
curl -i "$API_BASE/config" \
  -H 'X-Locale: ar' \
  -H 'If-None-Match: "REPLACE_WITH_RETURNED_ETAG"'
```

الرد إن لم تتغير البيانات: **HTTP 304 بدون body**. لا تُرسل هذا header في أول طلب إذا لم تكن لديك استجابة محفوظة.

يُعاد التحقق من البيانات كل طلب، بدون كاش داخلي مؤجل؛ تعديلات التواصل ونشر الشروط تظهر في الطلب التالي ويتغير `ETag`. إعدادات الملفات تحتاج دورة نشر/تحديث config cache المعتادة. الكاش خاص بالعميل ولا يُشارك بين الحسابات؛ الاستجابة تحمل `Vary` للغة والمصادقة. لا يعتمد `ETag` على وقت الطلب أو `X-Request-ID`.

## الأخطاء والتوافق

- `422 UNSUPPORTED_LOCALE`: لغة صريحة غير مدعومة.
- `429 RATE_LIMITED`: حد الطلبات العام؛ احترم `Retry-After`.
- لا تحتاج Authorization. وجود حساب محفوظ قد يؤثر على اللغة إذا لم ترسل `X-Locale`؛ ترتيب اللغة موضح في [عقد HTTP](http-contract.md).
- غياب سجل التواصل لا يمنع بدء التطبيق؛ ترجع حقوله فارغة.
- endpoints متطلبات التسجيل والتواصل والصفحات والدول الحالية تظل متاحة دون تغيير عقدها.
