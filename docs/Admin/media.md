# Admin — رفع الملفات وإدارتها

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. ارفع file مع purpose.
2. احفظ id ثم اربطه في media_ids عند إنشاء الرسالة أو حفظ الصفحة خلال 24 ساعة.
3. التنزيل الخاص يحتاج Bearer token في كل طلب؛ في المتصفح استخدم fetch ثم Blob URL.
4. احذف الملف غير المستخدم عبر DELETE؛ الملف المرتبط لا يُحذف بهذه العملية.

صور JPG/PNG/WebP يعاد ترميزها PNG. الدعم: 10 MiB، الصفحات: 5 MiB، الأبعاد حتى 4096×4096. PDF للدعم فقط وبعد ClamAV؛ عدم توفر الفاحص يعيد 503. حد 20 upload غير مرتبط و 200 MiB لكل رافع، و 10 طلبات رفع/دقيقة. رفع صفحة يتطلب pages.manage؛ رفع دعم للأدمن يتطلب support.read + support.reply. Vendor يرفع avatar فقط. [التخزين والتنظيف والتفعيل](../Shared/media-storage.md).

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/admin/media` |
| `GET` | `/api/v1/admin/media/{media}` |
| `DELETE` | `/api/v1/admin/media/{media}` |
| `GET` | `/api/v1/admin/media/{media}/download` |

## `POST /api/v1/admin/media`

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Media upload rate limit: 10 requests/minute per account.

### حقول الطلب

Content-Type: `multipart/form-data`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `purpose` | string | نعم | القيم: ["page", "support"]  |
| `file` | string | نعم | الصيغة: "binary" Maximum file size: 10240 kilobytes. |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/media" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -F 'purpose=page' \
  -F 'file=@./page-photo.jpg'
```

### مثال الاستجابة — HTTP 201

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": "019a0123-4567-7890-abcd-012345678901",
    "purpose": "page",
    "status": "ready",
    "name": "page-photo.jpg",
    "mime_type": "image/png",
    "size": 28412,
    "width": 1080,
    "height": 1920,
    "expires_at": "2026-10-12T10:00:00+00:00",
    "url": "http://localhost/api/v1/media/019a0123-4567-7890-abcd-012345678901",
    "download_url": "http://localhost/api/v1/admin/media/019a0123-4567-7890-abcd-012345678901/download"
  }
}
```

## `GET /api/v1/admin/media/{media}`

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `media` | path | نعم | `{"type": "string", "format": "uuid"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/media/019a0123-4567-7890-abcd-012345678901" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": "019a0123-4567-7890-abcd-012345678901",
    "purpose": "page",
    "status": "ready",
    "name": "page-photo.jpg",
    "mime_type": "image/png",
    "size": 28412,
    "width": 1080,
    "height": 1920,
    "expires_at": "2026-10-12T10:00:00+00:00",
    "url": "http://localhost/api/v1/media/019a0123-4567-7890-abcd-012345678901",
    "download_url": "http://localhost/api/v1/admin/media/019a0123-4567-7890-abcd-012345678901/download"
  }
}
```

## `DELETE /api/v1/admin/media/{media}`

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `media` | path | نعم | `{"type": "string", "format": "uuid"}` |

### مثال الطلب

```sh
curl --fail-with-body -X DELETE "$API_BASE/admin/media/019a0123-4567-7890-abcd-012345678901" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

## `GET /api/v1/admin/media/{media}/download`

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `media` | path | نعم | `{"type": "string", "format": "uuid"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/media/019a0123-4567-7890-abcd-012345678901/download" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

### مثال الاستجابة — HTTP 200

الاستجابة bytes للملف، وليست JSON. `Content-Type` هو نوع الصورة أو PDF، مع `X-Content-Type-Options: nosniff` و`Cache-Control: private, no-store`. التنزيل الخاص يحمل `Content-Disposition: attachment`.

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [MediaController.php](../../app/Http/Controllers/Shared/MediaController.php)
