# تخزين الميديا ودورة حياتها

[الفهرس](../README.md) · [Client API](../Client/media.md) · [Admin API](../Admin/media.md) · [الوصول العام والموقّع](media-access.md)

الملفات محفوظة على قرص خاص، وقاعدة البيانات تحفظ الملكية والغرض وحالة الفحص والارتباط. لا يُرسل مسار التخزين الداخلي للعميل. هذه الميزة ليس لها لوحة إدارة ملفات عامة لكل المستخدمين.

| الاستخدام | الرفع | الوصول |
|---|---|---|
| صورة الحساب | actor/profile/avatar | avatar_url موقّع لخمس دقائق |
| مرفق دعم | client أوadmin/media، purpose=support | تنزيل محمي بعضوية المحادثة أوصلاحيات الموظف |
| صورة صفحة | admin/media، purpose=page | عامة عند ارتباطها بالإصدار المنشور الحالي فقط |

## من الرفع إلى الحذف

1. رفع الملف ينشئ سجلًا ويجري الفحص قبل إرجاع ready. JPG/PNG/WebP يعاد ترميزها PNG مع إزالة البيانات الزائدة؛ امتداد الاسم الأصلي لا يحدد MIME النهائي.
2. الصور تحتاج GD يدعم JPEG/PNG/WebP. إعادة الترميز ليست فحص فيروسات. PDF للدعم فقط ويحتاج clamscan وقاعدة توقيعات صالحة؛ إذا كان الفاحص غير مهيأ فالطلب 503. عند تهيئة الفاحص يستخدم للصور أيضًا.
3. اربط id في رسالة أوصفحة خلال 24 ساعة. الرسالة تقبل حتى 5 مرفقات، والصفحة حتى 20. الربط والرسالة في transaction وتُفحص الملكية والغرض والحالة.
4. الملفات المرتبطة تصبح attached وتبقى مع الرسالة/تاريخ الصفحة. DELETE للملف المرتبط يعيد 409. حذف الملف غير المرتبط يسجل الحذف ثم يحاول التنظيف؛ prune يعيد محاولة الفشل.
5. استبدال avatar أوحذفه يلغي مرجعه فورًا؛ حذف bytes قد يتأخر حتى التنظيف. صور إصدارات الصفحات التاريخية لا تُتاح للعامة بمجرد وجودها في التخزين.

## الحدود

- Avatar: 2MiB وأبعاد حتى 2048×2048.
- Support: 10MiB؛ Page: 5MiB. أبعاد الصور حتى 4096×4096.
- Generic upload: 10 طلبات/دقيقة،20 ملفًا غير مرتبط و 200MiB لكل رافع وفق قيود الخدمة.
- التطبيق وPHP والـproxy جميعها تطبق حدود الحجم. إعداد Docker الحالي upload_max_filesize=10M وpost_max_size=12M وNginx client_max_body_size=12m.

## تنزيل مرفق من المتصفح

لا يعمل img src مع رابط الدعم الخاص دون Authorization. استخدم fetch وصورة Blob محلية، ثم حرر Object URL عند إزالة العنصر. مثال بعد الحصول على download_url من الرسالة:

```js
const response = await fetch(attachment.download_url, {
  headers: { Authorization: `Bearer ${clientToken}` },
});
if (!response.ok) throw new Error(`Download failed: ${response.status}`);
const objectUrl = URL.createObjectURL(await response.blob());
imageElement.src = objectUrl;
// عند إزالة الصورة من الواجهة:
// URL.revokeObjectURL(objectUrl);
```

رابط avatar الموقّع يستخدم كما يرجع كاملًا، ولا يحتاج Bearer. أعد profile للحصول على رابط جديد عند الانتهاء. روابط page قد ترجع 404 حتى النشر، أوعند الأرشفة/إزالة الملف من الإصدار المنشور.

## إعداد التشغيل

### Runtime setup and maintenance

- Run `php artisan migrate --no-interaction`, then
  `php artisan media:import-avatars --no-interaction`. Import is repeatable and
  registers existing local files without moving or re-encoding them. Legacy avatars
  remain readable during transition; new uploads use `MEDIA_DISK`.
- The runtime needs GD with JPEG, PNG and WebP support for image processing.
- Set `MEDIA_CLAMAV_BINARY` to the absolute path of `clamscan`, with a readable,
  current virus-signature database. Keep signatures updated through the deployment's
  ClamAV maintenance. Do not enable PDF uploads by bypassing the scan.
- `MEDIA_DISK=local` by default. Use private storage only. Local multi-node deployments
  need a shared volume. An S3 disk also needs its Laravel filesystem adapter installed
  and a private bucket policy; changing the default disk does not move existing files.
- Run the normal Laravel scheduler. `media:prune` runs every ten minutes, processes
  up to 500 records and retries deletion failures. Operators can run
  `php artisan media:prune --limit=1000 --no-interaction` manually.
- Historical page assets and sent chat attachments are retained; this command is
  not a conversation/history retention policy.


تأكد من توافر GD وClamAV في runtime الفعلي لكلapp/worker قبل تجربة الرفع؛ وجود متغيرات الإعداد لا يثبت تثبيت الإضافات. هذه الوثائق لا تثبّت أي حزم. خذ نسخة احتياطية من قاعدة البيانات والقرص الخاص معًا، واحتفظ بـAPP_KEY المستخدم لتشفير البيانات الأخرى.

مصدر التنفيذ: [ManageMedia](../../app/Actions/Shared/Media/ManageMedia.php) و[الإعدادات](../../config/media.php).
