# رحلة البائع كاملة

[فهرس البائع](README.md) · [تجهيز curl](../README.md)

1. اقرأ الدول العامة واختر country_id للهاتف.
2. أرسل `POST /api/v1/vendor/auth/otp/request` ثم `/auth/otp/verify` بنفس الدولة والهاتف وchallenge والكود. الأمثلة والرد الكامل في [المصادقة](authentication.md). الرقم مرتبط بنوع حساب؛ رقم Client لا يدخل Vendor تلقائيًا.
3. احفظ data.token واقرأ user.status. البائع الجديد pending؛ قد يرجع next_step=home، لكن ذلك لا يعني اعتماده. اعرض شاشة انتظار الموافقة. الملف وإدارة الجلسات والخروج متاحة وفق مسارات الحساب، بينما الميزات التي تتطلب active تعيد 403.
4. الأدمن يعتمد الحساب عبر users/status بصلاحية vendors.approve. اقرأ profile مجددًا للتعرف على active؛ لا توجد API للبائع لاعتماد نفسه.
5. حدّث [الملف والصورة](profile.md). لا يوجد vendor complete-profile أوclient terms flow أوaccount closure endpoint. لا ترسل حقولًا غير موثقة ضمن profile.
6. سجّل [جهاز Push](push-notifications.md)، ثم حمّل [الإشعارات](notifications.md) وصِل [Socket](realtime.md). في إعادة الاتصال، مزامنة REST تسترجع التغييرات الفائتة.
7. استخدم [المحفظة](wallet.md) و[الدفع](payments.md) بمفتاح Idempotency ومبالغ minor units. التسوية وحدها تزيد الرصيد. لا تمنح Vendor وصولًا لخدمات حساب التاجر الإدارية في MyFatoorah.
8. لإدارة الوصول استخدم [الجلسات](sessions.md) و[تغيير الهاتف](phone-change.md). لا توجد مسارات reauthenticate الخاصة بالعميل تحت Vendor.
9. عند الخروج امسح التوكن وافصل Socket. لا توجد vendor support chat أوgeneric media endpoints حاليًا؛ avatar له endpoint مستقل، ووسائل التواصل العامة متاحة دون توكن البائع.
