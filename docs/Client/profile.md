# Client — الملف الشخصي وصورته

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اقرأ profile بعد الدخول.
2. أرسل PATCH بالحقول المعدّلة فقط: name وlocale وtimezone.
3. ارفع avatar كـmultipart واستبدل رابط الصورة بالراجع من السيرفر.
4. عند انتهاء رابط الصورة اطلب profile مجددًا. DELETE للصورة لا يحذف الحساب.

لا يوجد تعديل Email للعميل ولا تغيير phone عبر PATCH profile. الصور الجديدة تحتاج GD في runtime؛ انظر [تشغيل الميديا](../Shared/media-storage.md).

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/client/profile` |
| `PATCH` | `/api/v1/client/profile` |
| `POST` | `/api/v1/client/profile/avatar` |
| `DELETE` | `/api/v1/client/profile/avatar` |

## أمثلة التدفق بالتفصيل

### 4.1 قراءة الملف — GET /profile

```sh
curl --fail-with-body -X GET "$CLIENT_BASE/profile" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 42,
    "name": "Ahmed Ali",
    "phone": "+201012345678",
    "phone_country_id": 1,
    "phone_verified_at": "2026-10-11T10:00:00+00:00",
    "type": "client",
    "status": "active",
    "locale": "en",
    "timezone": "Africa/Cairo",
    "has_avatar": false,
    "avatar_url": null,
    "profile_completed": true,
    "terms_version": "1"
  }
}
```

استخدمه عند استعادة التطبيق للتحقق من الجلسة وتحديث بيانات المستخدم. يمكن قراءة الملف بتوكن صالح حتى للحساب غير النشط، بينما تعديلات الملف تتطلب حسابًا نشطًا.

### 4.2 تحديث الملف — PATCH /profile

```sh
curl --fail-with-body -X PATCH "$CLIENT_BASE/profile" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"name":"Ahmed Hassan","locale":"en","timezone":"Africa/Cairo"}'
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": "Your profile has been updated.",
  "data": {
    "id": 42,
    "name": "Ahmed Hassan",
    "phone": "+201012345678",
    "phone_country_id": 1,
    "phone_verified_at": "2026-10-11T10:00:00+00:00",
    "type": "client",
    "status": "active",
    "locale": "en",
    "timezone": "Africa/Cairo",
    "has_avatar": false,
    "avatar_url": null,
    "profile_completed": true,
    "terms_version": "1"
  }
}
```

أرسل الحقول المتغيرة فقط. يمكن مسح timezone بـ`null`. حقول الهاتف والبريد وكلمة المرور والنوع والحالة والصلاحيات والرصيد محظورة هنا. تغيير الهاتف له تدفق منفصل.

### 4.3 رفع الصورة — POST /profile/avatar

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/profile/avatar" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN" \
  -F "avatar=@/absolute/path/avatar.jpg"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 42,
    "name": "Ahmed Ali",
    "phone": "+201012345678",
    "phone_country_id": 1,
    "phone_verified_at": "2026-10-11T10:00:00+00:00",
    "type": "client",
    "status": "active",
    "locale": "en",
    "timezone": "Africa/Cairo",
    "has_avatar": true,
    "avatar_url": "http://localhost/api/v1/media/019a0123-4567-7890-abcd-012345678901/avatar?expires=1791713400&signature=EXAMPLE",
    "profile_completed": true,
    "terms_version": "1"
  }
}
```

JPEG/PNG/WebP فقط، حتى 2048KB وبُعد أقصى 2048×2048. لا تضبط Content-Type يدويًا؛ curl ينشئ multipart boundary. الصورة خاصة والرابط المؤقت صالح 5 دقائق. الرابط أعلاه تمثيلي: استخدم قيمة `avatar_url` كما هي، وأعد جلب الملف لتجديدها؛ لا تبنِ المسار أو التوقيع بنفسك.

### 4.4 حذف الصورة — DELETE /profile/avatar

```sh
curl --fail-with-body -X DELETE "$CLIENT_BASE/profile/avatar" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 42,
    "name": "Ahmed Ali",
    "phone": "+201012345678",
    "phone_country_id": 1,
    "phone_verified_at": "2026-10-11T10:00:00+00:00",
    "type": "client",
    "status": "active",
    "locale": "en",
    "timezone": "Africa/Cairo",
    "has_avatar": false,
    "avatar_url": null,
    "profile_completed": true,
    "terms_version": "1"
  }
}
```

تكرار الحذف آمن؛ النتيجة `has_avatar: false` و`avatar_url: null`.

### انتقال تخزين الصورة إلى Media

روابط الصور الجديدة تمر عبر `/api/v1/media/{media}/avatar` بتوقيع مؤقت، استخدم الرابط الراجع من profile كما هو. حذف/استبدال الصورة يلغي مرجعها فورًا، وحذف الملف الفعلي تعالجه `media:prune`. [تفاصيل التشغيل](../Shared/media-storage.md).

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [AvatarController.php](../../app/Http/Controllers/Shared/AvatarController.php)
- [ProfileController.php](../../app/Http/Controllers/Shared/ProfileController.php)
