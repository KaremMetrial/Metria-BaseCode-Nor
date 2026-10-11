# Admin — استرداد المدفوعات

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اختر دفعة مسوّاة وصلاحية payments.refund.
2. أرسل amount ضمن المتبقي القابل للاسترداد وIdempotency-Key.
3. النتيجة pending تحجز خصم المحفظة إلى تأكيد المزود.
4. الفشل المؤكد يحرر الحجز؛ النتيجة غير المؤكدة تحتاج reconciliation.

قراءة جميع payments منفصلة عن صلاحية الاسترداد. لا تتجاوز إجمالي المدفوع أو المبلغ المسترد/المحجوز سابقًا.

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/admin/payments/{payment}/refunds` |

## `POST /api/v1/admin/payments/{payment}/refunds`

Requires an Idempotency-Key header. Supports partial refunds up to the remaining refundable amount. A pending result reserves the amount until provider confirmation; a confirmed failed refund releases that reservation. An uncertain provider outcome must be retried with the same key.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `payments.refund`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `payment` | path | نعم | `{"type": "integer"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `amount` | integer | نعم | min: 1; max: 99999999 Positive integer in the currency’s smallest unit (100 = 1.00 for EGP/USD; 1000 = 1.000 for KWD/BHD/OMR/JOD). |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/payments/42/refunds" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Idempotency-Key: v1-admin-payments-refund-001' \
  -H 'Content-Type: application/json' \
  --data '{"amount":500}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": "The refund request has been processed.",
  "data": {
    "id": 11,
    "amount": 500,
    "status": "pending"
  }
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [RefundController.php](../../app/Http/Controllers/Admin/RefundController.php)
