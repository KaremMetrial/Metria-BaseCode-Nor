# مرجع OpenAPI وتحديثه

[الفهرس](../README.md) · [كل endpoints](../ENDPOINTS.md)

`GET /docs/api` يعرض Scramble و`GET /docs/api.json` يعرض OpenAPI3.1. الوصول مفتوح في local/development، وفي البيئات الأخرى يتطلب حساب super-admin نشطًا بتوكن Bearer. هذه مسارات ويب خارج `/api/v1`.

بعد تجهيز قاعدة البيانات والهجرات:

```sh
php artisan scramble:export --fail-on-unknown --no-interaction
```

الملف الافتراضي `storage/app/private/openapi.json`. إن كان المرجع مخزنًا في cache بعد تغيير العقود:

```sh
php artisan scramble:clear --no-interaction
php artisan scramble:cache --no-interaction
```

راجع المسارات بواسطة `php artisan route:list --except-vendor`. فهرس هذا المجلد يغطي 202 عملية API من التصدير الحالي، دون عدHEAD المضاف لـGET كميزة منفصلة. الواجهات المشروطة بـprovider أوحالة الحساب تظل مسجلة لكنها تحتاج تهيئة/صلاحيات مناسبة.

مصادر توثيق الحقول هي Form Requests وResources وامتدادات Scramble في [app/Support](../../app/Support)، وأمثلة خدمات التاجر في [myfatoorah-examples.json](../../resources/myfatoorah-examples.json). الردود هنا أمثلة تركيبية وليست ضمانًا لقيم IDs أوحالة مزود خارجي.

## حدود الاستدلال التلقائي الحالية

أثناء مطابقة أمثلة الدليل مع التصدير والكود، ظهرت مواضع يصف فيها Scramble نوع PHP array بصورة أوسع أوأضيق من JSON الفعلي:

- PATCH notification-preferences: preferences كائن بمفاتيح security_push وtransactional_push وmarketing_push، رغم وصف التصدير الحالي له كـarray.
- GET contact-settings: data كائن إعدادات؛ يصفه التصدير الحالي كـarray.
- MyFatoorahEntityResource.status قد يكون null إذا لم تُستخلص حالة، رغم نوع string في التصدير الحالي.

تعتمد الأمثلة على Controller/Resource في هذه المواضع. راع ذلك عند توليد client من OpenAPI؛ لم يُعدل كود التطبيق أوالتصدير الأصلي ضمن مهمة التوثيق هذه.
