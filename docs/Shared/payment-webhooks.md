# Shared — استقبال أحداث بوابات الدفع

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. يرسل مزود الدفع الحدث إلى اسم provider المهيأ في السيرفر.
2. السيرفر يتحقق من التوقيع على الجسم الخام والبيئة ثم يحفظ المعالجة بصورة قابلة للتكرار.
3. التسوية تغيّر الدفعة والـledger وnotification outbox في المعاملة المناسبة.
4. 200 يقر بالاستلام؛ الأحداث غير المرتبطة لا تعني تحريك أموال.

لا Bearer token ولا توقيع من تطبيق العميل. Stripe يستخدم Stripe-Signature؛ MyFatoorah يستخدم MyFatoorah-Signature. حد الجسم 256 KiB. استخدم sandbox/provider tooling لاختبار التوقيع، لا الأمثلة بتوقيع وهمي.

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/webhooks/payments/{provider}` |

## `POST /api/v1/webhooks/payments/{provider}`

Accepts Stripe events with Stripe-Signature and MyFatoorah webhook v2 events with MyFatoorah-Signature. Maximum body size is 256 KiB. Payment and refund settlement is idempotent. MyFatoorah business events mark integration records for refresh; they never credit wallets.

Webhook rate limit: 300 requests/minute per IP.

For provider=stripe, Stripe-Signature is required. For provider=myfatoorah, MyFatoorah-Signature is required and the body must use webhook v2 Event/Data fields. Configure the secure key in the merchant portal. MyFatoorah signatures cover event-specific fields; settlement additionally fetches authoritative payment/refund details.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `provider` | path | نعم | `{"type": "string"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

الجسم خام بعقد المزود. Stripe: id/type/livemode/data.object؛ MyFatoorah webhook v2: Event/Data. التوقيع جزء أساسي من العقد وليس Bearer.


### مثال الطلب

احفظ raw body لحدث Stripe sandbox من أداة المزود في stripe-event.json، واضبط STRIPE_SIGNATURE من توقيع ذلك الجسم. التوقيع مرتبط بالبايتات والوقت؛ لا تعِد تنسيق JSON بعد توقيعه ولا تستخدم توقيعًا منتهيًا.

```sh
curl --fail-with-body -X POST "$API_BASE/webhooks/payments/stripe" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H "Stripe-Signature: $STRIPE_SIGNATURE" \
  --data-binary @stripe-event.json
```

مثال تركيب الجسم لدفعة sandbox مرتبطة؛ استبدل provider ID بالمعرف الحقيقي قبل توليد التوقيع:

```json
{"id":"evt_example","type":"payment_intent.succeeded","livemode":false,"data":{"object":{"id":"pi_example","object":"payment_intent","status":"succeeded","amount":1500,"amount_received":1500,"currency":"egp","metadata":{"payment_uuid":"11111111-1111-4111-8111-111111111111"}}}}
```

هذا مثال بنية فقط: أضف التوقيع الحقيقي على **نفس raw body**. الطلب بلا توقيع مزود صحيح سيُرفض؛ انظر ملاحظات التوقيع أعلاه.

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

## التعامل مع الفشل

400 INVALID_WEBHOOK: تحقق من التوقيع والبيئة والجسم الخام. 413: الجسم أكبر من الحد. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [WebhookController.php](../../app/Http/Controllers/WebhookController.php)
