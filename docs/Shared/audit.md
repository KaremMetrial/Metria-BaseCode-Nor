# التتبع وسجل التدقيق

[الفهرس](../README.md) · [HTTP](http-contract.md) · [تشغيل المشروع](../Operations/README.md)

كل HTTP request له X-Request-ID مُرجع في الرد، ويمكن إرسال قيمة 8–64 حرفًا آمنة لتجميع محاولة في سجلات العميل والخادم. عند خطأ 500 احفظ هذا المعرف والوقت والمسار وكود الخطأ؛ لا تسجل Authorization أوOTP أوبيانات البطاقة.

AuditLogger يكتب audit_logs باستخدام action وactor_user_id والموضوع وold_values/new_values/context وrequest_id. بيانات IP وUser-Agent تؤخذ لطلبات HTTP، ولا تُنسب مهام queue إلى طلب قديم. RedactSecrets يحجب مفاتيح الأسرار والرموز وكلمات المرور وأرقام الهواتف والبريد وبيانات البطاقة، مع معالجة nested values.

لا توجد API لقراءة أوتعديل Audit logs. يراجعها مشغل مخول عبر أدوات قاعدة البيانات المعتمدة. تشمل مواضع التدقيق المسجلة الدخول والخروج وتغيير الهاتف والحالة والاعتماد والأدوار والتسويات المالية ونشر الصفحات وإدارة الدعم والتواصل وعمليات MyFatoorah. قائمة enum تسجل المفردات الممكنة؛ وجود قيمة فيها لا يثبت أن كل تعديل مباشر بقاعدة البيانات يُراجع تلقائيًا.

مثال context مخزن بعد الحجب، وليس HTTP response:

```json
{"phone":"[REDACTED]","token":"[REDACTED]","reason":"Approved compensation"}
```

مصادر: [AuditLogger](../../app/Services/Audit/AuditLogger.php)، [RedactSecrets](../../app/Services/Audit/RedactSecrets.php)، [AuditAction](../../app/Enums/AuditAction.php).
