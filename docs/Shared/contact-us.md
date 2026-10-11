# Shared — إرسال رسالة تواصل معنا

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اقرأ contact-settings لعرض وسائل التواصل ونصوصها.
2. أرسل اسمًا وهاتفًا ودولة وموضوعًا ورسالة وrequest_id UUID.
3. احتفظ بالـreference الراجع. أعد نفس request_id والبيانات عند timeout.

لا يحتاج Email. هذا نموذج رسالة باتجاه واحد؛ الرد التفاعلي يكون عبر support chat. لا توجد قراءة عامة لرسالة بالمرجع. Client token اختياري لربط الرسالة بالحساب؛ التوكن غير الصحيح يُرفض. إرسال هاتف لا يثبت ملكيته. حد الإرسال 3/IP/min مشترك مع نموذج المتصفح.

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/contact-messages` |
| `GET` | `/api/v1/contact-settings` |

## `POST /api/v1/contact-messages`

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Contact submission rate limit: 3 requests/minute per IP, shared with the web form.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `request_id` | string | نعم | الصيغة: "uuid"  |
| `name` | string | نعم | maxLength: 255  |
| `country_id` | integer | نعم |   |
| `phone` | string | نعم | maxLength: 40  |
| `subject` | string | نعم | maxLength: 200  |
| `message` | string | نعم | maxLength: 5000  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/contact-messages" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H 'Content-Type: application/json' \
  --data '{"request_id":"11111111-1111-4111-8111-111111111111","name":"Ahmed Ali","country_id":1,"phone":"+201012345678","subject":"Service availability","message":"Is the service available in Cairo?"}'
```

### مثال الاستجابة — HTTP 201

```json
{
  "success": true,
  "message": "Your message has been received. Keep the reference for follow-up.",
  "data": {
    "reference": "11111111-1111-4111-8111-111111111111"
  }
}
```

## `GET /api/v1/contact-settings`

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/contact-settings" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "phone": "+201012345678",
    "whatsapp": "+201012345678",
    "social_links": [
      {
        "label": "Website",
        "url": "https://example.test"
      }
    ],
    "intro": "Contact our team",
    "address": "Cairo",
    "working_hours": "Sunday–Thursday, 9–5"
  }
}
```

## التعامل مع الفشل

401: التوكن الاختياري غير صالح. 403: الحساب المرفق ليس Client نشطًا. 409: request_id أُعيد ببيانات مختلفة. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [ContactMessageController.php](../../app/Http/Controllers/Shared/ContactMessageController.php)
- [ContactSettingController.php](../../app/Http/Controllers/Shared/ContactSettingController.php)

## نموذج المتصفح

`GET /pages/contact-us?locale=ar` يعرض HTML ونموذجًا بالبلدان ووسائل التواصل عند نشر الصفحة. يستخدم النموذج `POST /pages/contact-us/message` خارج API. مثال بيانات form-urlencoded، مع `_token` من جلسة المتصفح:

```text
_token=CSRF_FROM_RENDERED_FORM
locale=ar
request_id=11111111-1111-4111-8111-111111111111
name=Ahmed Ali
country_id=1
phone=+201012345678
subject=Service availability
message=Is the service available in Cairo?
```

الرد الناجح HTTP302 وLocation يشير إلى `/pages/contact-us?locale=ar`، مع contact_reference في session flash تعرضه الصفحة. لا يرجع غلاف API JSON. أرسل session cookie وCSRF الصحيحين؛ بدون CSRF صالح يُرفض الطلب. validation يعيد الأخطاء للصفحة، وعدم نشر contact-us يعيد 404. هذا النموذج يرسل كزائر ولا يربط Bearer بالعميل؛ لربط حساب استخدم API أعلاه.
