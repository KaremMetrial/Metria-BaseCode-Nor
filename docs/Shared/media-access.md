# Shared — الوصول العام للصور والروابط الموقعة

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

رابط صورة الصفحة عام فقط عندما تكون مرتبطة بالإصدار المنشور الحالي. رابط avatar موقّع لمدة خمس دقائق ويُرجع من profile؛ استخدمه كاملًا دون تعديل signature/expires. استبدال الصورة أو إغلاق/تعطيل الحساب يمنع الوصول عبر رابط avatar الجديد. لا تستخدم هذه المسارات لتنزيل مرفقات الدعم؛ لذلك مسارات محمية داخل Client/Admin.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/media/{media}` |
| `GET` | `/api/v1/media/{media}/avatar` |

## `GET /api/v1/media/{media}`

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `media` | path | نعم | `{"type": "string", "format": "uuid"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/media/019a0123-4567-7890-abcd-012345678901" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en'
```

### مثال الاستجابة — HTTP 200

الاستجابة bytes للملف، وليست JSON. `Content-Type` هو نوع الصورة أو PDF، مع `X-Content-Type-Options: nosniff` و`Cache-Control: private, no-store`. التنزيل الخاص يحمل `Content-Disposition: attachment`.

## `GET /api/v1/media/{media}/avatar`

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.\n\nUse the complete signed avatar_url from the profile response; it expires after five minutes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `media` | path | نعم | `{"type": "string", "format": "uuid"}` |
| `signature` | query | نعم | `{"type": "string"}` |
| `expires` | query | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body "$AVATAR_URL" --output avatar.png
```

استخدم `avatar_url` الراجع من profile كاملًا بدل الرابط المختصر أعلاه؛ `signature` و`expires` يولدهما السيرفر. دون التوقيع الصحيح يرجع 403.

### مثال الاستجابة — HTTP 200

الاستجابة bytes للملف، وليست JSON. `Content-Type` هو نوع الصورة أو PDF، مع `X-Content-Type-Options: nosniff` و`Cache-Control: private, no-store`. التنزيل الخاص يحمل `Content-Disposition: attachment`.

## التعامل مع الفشل

403: توقيع avatar غير صالح أومنتهٍ. 404: الملف غير متاح وفق الملكية أوحالة النشر/الحساب. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [MediaController.php](../../app/Http/Controllers/Shared/MediaController.php)
