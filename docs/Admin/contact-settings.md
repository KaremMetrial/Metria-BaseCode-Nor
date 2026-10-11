# Admin — إعدادات التواصل

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اقرأ الإعدادات واحفظ version.
2. PATCH بالقيم التي تغيرت مع version الحالي.
3. حدّث النسخة المحلية من الاستجابة؛ 409 يعني إعادة القراءة والمراجعة.

phone/whatsapp بصيغة E.164 أو null. social_links تستبدل القائمة كاملة؛ translations تدمج اللغات المرسلة. الروابط HTTP(S). الإعدادات العامة تعرض اللغة المختارة دون نسخ الإدارة. يلزم contact.settings.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/contact-settings` |
| `PATCH` | `/api/v1/admin/contact-settings` |

## `GET /api/v1/admin/contact-settings`

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `contact.settings`.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/contact-settings" \
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
    "version": 1,
    "settings": {
      "phone": "+201012345678",
      "whatsapp": "+201012345678",
      "social_links": [
        {
          "label": "Website",
          "url": "https://example.test"
        }
      ],
      "translations": {
        "en": {
          "intro": "Contact our team",
          "address": "Cairo",
          "working_hours": "Sunday–Thursday, 9–5"
        },
        "ar": {
          "intro": "تواصل مع فريقنا",
          "address": "القاهرة",
          "working_hours": "الأحد–الخميس، 9–5"
        }
      }
    }
  }
}
```

## `PATCH /api/v1/admin/contact-settings`

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `contact.settings`.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `version` | integer | نعم | min: 1  |
| `phone` | string/null | اختياري/شرطي | pattern: "^\\+[1-9][0-9]{6,14}$"  |
| `whatsapp` | string/null | اختياري/شرطي | pattern: "^\\+[1-9][0-9]{6,14}$"  |
| `social_links` | array | اختياري/شرطي | maxItems: 10  |
| `social_links[].label` | string | عند إرسال الأب | maxLength: 100  |
| `social_links[].url` | string | عند إرسال الأب | الصيغة: "uri"; maxLength: 2048  |
| `translations` | object | اختياري/شرطي |   |
| `translations.en` | object | اختياري/شرطي |   |
| `translations.en.intro` | string/null | اختياري/شرطي | maxLength: 2000  |
| `translations.en.address` | string/null | اختياري/شرطي | maxLength: 2000  |
| `translations.en.working_hours` | string/null | اختياري/شرطي | maxLength: 2000  |
| `translations.ar` | object | اختياري/شرطي |   |
| `translations.ar.intro` | string/null | اختياري/شرطي | maxLength: 2000  |
| `translations.ar.address` | string/null | اختياري/شرطي | maxLength: 2000  |
| `translations.ar.working_hours` | string/null | اختياري/شرطي | maxLength: 2000  |

### مثال الطلب

```sh
curl --fail-with-body -X PATCH "$API_BASE/admin/contact-settings" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"version":1,"phone":"+201012345678","whatsapp":"+201012345678","social_links":[{"label":"Website","url":"https://example.test"}],"translations":{"en":{"intro":"Contact our team","address":"Cairo","working_hours":"Sunday–Thursday, 9–5"},"ar":{"intro":"تواصل مع فريقنا","address":"القاهرة","working_hours":"الأحد–الخميس، 9–5"}}}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "version": 2,
    "settings": {
      "phone": "+201012345678",
      "whatsapp": "+201012345678",
      "social_links": [
        {
          "label": "Website",
          "url": "https://example.test"
        }
      ],
      "translations": {
        "en": {
          "intro": "Contact our team",
          "address": "Cairo",
          "working_hours": "Sunday–Thursday, 9–5"
        },
        "ar": {
          "intro": "تواصل مع فريقنا",
          "address": "القاهرة",
          "working_hours": "الأحد–الخميس، 9–5"
        }
      }
    }
  }
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [ContactSettingController.php](../../app/Http/Controllers/Admin/ContactSettingController.php)
