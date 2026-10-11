# دليل المطور — Metrial

هذا الدليل يصف السلوك الموجود في الكود ومسارات التطبيق بتاريخ 11 أكتوبر 2026. الشرح بالعربية، وأسماء الحقول والقيم كما تستعملها API. لكل ميزة ملف مستقل يوضح التسلسل، endpoint، المصادقة، المدخلات، cURL، الرد، والأخطاء المهمة.

ابدأ بـ[رحلة العميل الكاملة](Client/full-flow.md)، أو [رحلة الأدمن](Admin/full-flow.md)، أو [رحلة البائع](Vendor/full-flow.md). يوجد [فهرس لكل 203 عملية API](ENDPOINTS.md)، ودليل [HTTP والأخطاء](Shared/http-contract.md) مشترك بينها.

| المجلد | المحتوى |
|---|---|
| [Client](Client/README.md) | التسجيل والدخول بالهاتف، الملف، الأجهزة والإشعارات، المحفظة والدفع، الدعم والميديا وإغلاق الحساب |
| [Admin](Admin/README.md) | الحساب الإداري، الصلاحيات، المستخدمون، المحتوى، الدعم، البيانات المرجعية والماليات |
| [Admin/MyFatoorah](Admin/MyFatoorah/README.md) | خدمات حساب التاجر وعمليات البوابة المتقدمة |
| [Vendor](Vendor/README.md) | الدخول بالهاتف، انتظار الاعتماد، الحساب والإشعارات والمحفظة والدفع |
| [Shared](Shared/README.md) | الميزات العامة وعقود التكامل المشتركة والميديا وOutbox |
| [Operations](Operations/README.md) | إعداد المشروع والخدمات والتشغيل والاختبارات والنشر |

## تجهيز أمثلة curl

العنوان المحلي الحالي هو `http://localhost/api/v1`. غيّره إلى عنوان بيئتك. هذه الأوامر للتنفيذ محليًا عند التكامل؛ إنشاء دفعة أو تعديل بيانات له أثر فعلي في البيئة المختارة.

```sh
export API_BASE='http://localhost/api/v1'
export CLIENT_BASE="$API_BASE/client"
export ADMIN_BASE="$API_BASE/admin"
export VENDOR_BASE="$API_BASE/vendor"
export CLIENT_TOKEN='REPLACE_WITH_RETURNED_CLIENT_TOKEN'
export ADMIN_TOKEN='REPLACE_WITH_RETURNED_ADMIN_TOKEN'
export VENDOR_TOKEN='REPLACE_WITH_RETURNED_VENDOR_TOKEN'
export TOKEN="$CLIENT_TOKEN"
```

`TOKEN` اختصار تستخدمه أمثلة Client المفصلة. احفظ قيمة التوكن كاملة بما فيها الجزء السابق لـ`|`. Public endpoints لا تحتاج Authorization. المسارات داخل Actor تتطلب نوع الحساب نفسه؛ الأدمن لا يستخدم توكنه بدل العميل داخل `/client`.

الأرقام والأسماء والردود في الملفات **أمثلة واقعية توضيحية وليست بيانات مستخدمين أو نتائج مزود حي**. استخدم IDs وchallenge وOTP والروابط والتوكنات الراجعة من بيئتك. لا تثبّت بلدًا أو إصدار شروط أو معرف محفظة داخل التطبيق. الأمثلة غالبًا باللغة الإنجليزية عبر `X-Locale: en` حتى يسهل مقارنة الرد؛ استخدم `ar` لواجهتك العربية.

مثال مفتاح Idempotency ثابت لإعادة **العملية نفسها فقط**؛ لكل عملية جديدة أنشئ UUID جديدًا. لا تكرر المفاتيح الثابتة في الأمثلة لعمليات مختلفة. [تفاصيل منع التكرار](Shared/outbox-and-idempotency.md).

## حدود الميزات الحالية

- العميل يستخدم الهاتف فقط، دون Email أو Password. التسجيل والدخول نفس مسار OTP. لا يوجد refresh-token endpoint.
- Contact Us نموذج إرسال رسالة؛ Support Chat محادثة داخل حساب العميل. لا توجد محادثات للبائع حاليًا.
- لا توجد واجهة API عامة لإدارة قاموس الأدوار أو إنشاء Admin أو قراءة Audit logs.
- وسائل SMS والدفع وFCM تحتاج إعدادًا مستقلًا؛ وجود endpoint لا يعني تفعيل المزود. الميديا تحتاج GD وClamAV للـPDF.
- الشروط والسياسات تُزرع كمسودات وتحتاج كتابة المحتوى ونشره من الأدمن.

## تحديث التوثيق

عند تغيير route أو request أو resource أو workflow، حدّث ملف الميزة وفهرس endpoints وأعد تصدير [OpenAPI](Operations/openapi.md). أسماء الملفات والمجلدات تطابق أنواع الحسابات؛ السلوك المشترك موثق في Shared وتربطه أدلة الحسابات.
