# Vendor — الملف الشخصي وصورته

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
| `GET` | `/api/v1/vendor/profile` |
| `PATCH` | `/api/v1/vendor/profile` |
| `POST` | `/api/v1/vendor/profile/avatar` |
| `DELETE` | `/api/v1/vendor/profile/avatar` |

## `GET /api/v1/vendor/profile`

Available to pending accounts as well as active accounts.

Requires a bearer token for the Vendor actor.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/vendor/profile" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 57,
    "name": "Vendor User",
    "phone": "+201012345678",
    "phone_country_id": 1,
    "phone_verified_at": "2026-10-11T10:00:00+00:00",
    "type": "vendor",
    "status": "active",
    "locale": "en",
    "timezone": "Africa/Cairo",
    "has_avatar": false,
    "avatar_url": null,
    "email": null
  }
}
```

## `PATCH /api/v1/vendor/profile`

Updates only the supplied name, preferred locale and timezone. Use the separate phone verification flow to change a phone number.

Requires a bearer token for the Vendor actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `name` | string | اختياري/شرطي | maxLength: 255  |
| `locale` | string | اختياري/شرطي | القيم: ["en", "ar"]  |
| `timezone` | string/null | اختياري/شرطي |  IANA timezone identifier, or null. |

### مثال الطلب

```sh
curl --fail-with-body -X PATCH "$API_BASE/vendor/profile" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"name":"Ahmed Ali","locale":"en","timezone":"Africa/Cairo"}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": "Your profile has been updated.",
  "data": {
    "id": 57,
    "name": "Vendor User",
    "phone": "+201012345678",
    "phone_country_id": 1,
    "phone_verified_at": "2026-10-11T10:00:00+00:00",
    "type": "vendor",
    "status": "active",
    "locale": "en",
    "timezone": "Africa/Cairo",
    "has_avatar": false,
    "avatar_url": null,
    "email": null
  }
}
```

## `POST /api/v1/vendor/profile/avatar`

Accepts a JPEG, PNG or WebP image up to 2 MB and 2048 pixels per dimension. Stores the file privately and replaces the previous image.

Requires a bearer token for the Vendor actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Additional rate limit: 5 requests/minute per IP.

### حقول الطلب

Content-Type: `multipart/form-data`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `avatar` | string | نعم | الصيغة: "binary" Maximum file size: 2048 kilobytes. |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/vendor/profile/avatar" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN" \
  -F 'avatar=@./portrait.png'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 57,
    "name": "Vendor User",
    "phone": "+201012345678",
    "phone_country_id": 1,
    "phone_verified_at": "2026-10-11T10:00:00+00:00",
    "type": "vendor",
    "status": "active",
    "locale": "en",
    "timezone": "Africa/Cairo",
    "has_avatar": true,
    "avatar_url": "http://localhost/api/v1/media/019a0123-4567-7890-abcd-012345678901/avatar?expires=1791713100&signature=REPLACE_WITH_RETURNED_SIGNATURE",
    "email": null
  }
}
```

## `DELETE /api/v1/vendor/profile/avatar`

Clears the image reference and removes the stored image after the transaction commits.

Requires a bearer token for the Vendor actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### مثال الطلب

```sh
curl --fail-with-body -X DELETE "$API_BASE/vendor/profile/avatar" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 57,
    "name": "Vendor User",
    "phone": "+201012345678",
    "phone_country_id": 1,
    "phone_verified_at": "2026-10-11T10:00:00+00:00",
    "type": "vendor",
    "status": "active",
    "locale": "en",
    "timezone": "Africa/Cairo",
    "has_avatar": false,
    "avatar_url": null,
    "email": null
  }
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [AvatarController.php](../../app/Http/Controllers/Shared/AvatarController.php)
- [ProfileController.php](../../app/Http/Controllers/Shared/ProfileController.php)
