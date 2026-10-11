# أنواع الحسابات والأدوار والصلاحيات

[فهرس الأدمن](README.md) · [إدارة المستخدمين](users.md)

type هو admin/client/vendor. الدور مثل support-agent يُسنَد لأدمن ولا يغيّر type. التوكن يخضع لنوع المسار وحالة الحساب ثمصلاحية العملية. super-admin يتجاوز فحوص الصلاحيات عبر Gate، ولا يجعل استخدام مسارات نوع آخر صحيحًا.

| الدور المزروع | صلاحياته الأساسية |
|---|---|
| super-admin | تجاوز صلاحيات Gate؛ لا يحتاج نسخ قائمة كلpermission |
| finance-admin | payments.read, payments.refund, wallets.read |
| support-agent | users.read, users.block, contact.read, contact.manage, support.read, support.reply, support.manage |

| المجال | الصلاحيات |
|---|---|
| المحتوى | pages.read, pages.manage, pages.publish |
| إعدادات التواصل | contact.settings |
| المواقع | locations.read, locations.manage |
| التصنيفات | categories.read, categories.create, categories.update, categories.delete |
| البائعون | vendors.approve, vendors.reject |
| تسوية المحافظ | wallets.adjust مع wallets.credit أوwallets.debit |
| خدمات MyFatoorah | myfatoorah.manage |

الأدوار المزروعة لا تحصل تلقائيًا على كل مجال في الجدول الثاني. أعد تشغيل RbacSeeder أثناء التهيئة/النشر عند إضافة التعريفات؛ فهو يزامن أدواره المعرفة وقد يستبدل تخصيص صلاحياتها. التخصيص التشغيلي يحتاج إجراء إدارة معتمد؛ لا توجد endpoint لإدارة Permission أوRole CRUD.

`POST /api/v1/admin/users/{user}/roles` يحتاج super-admin والهدف Admin. الطلب `{"role":"support-agent"}` يضيف الدور ولا يزيل السابق؛ الرد الكامل في users. تغيير حالة نفسك أوأدمن آخر مرفوض عبر endpoint status.

مصادر: [PermissionRegistry](../../app/Support/Access/PermissionRegistry.php)، [RoleRegistry](../../app/Support/Access/RoleRegistry.php)، [RbacSeeder](../../database/seeders/RbacSeeder.php).
