# Vendor — الدفع ومتابعة حالته

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اختر العملة والمبلغ بوحدتها الصغرى وحدد المزود المهيأ.
2. أنشئ الدفعة بـIdempotency-Key فريد للعملية.
3. Stripe: استخدم client_secret مع SDK؛ MyFatoorah: افتح payment_url.
4. عند عودة المستخدم اقرأ payment؛ redirect وحده ليس إثبات دفع.
5. انتظر webhook موقّعًا أو reconciliation ثم حدّث المحفظة.

المالك وحده يرى client_secret أثناء pending/processing. أعد المفتاح والطلب نفسهما بعد timeout/503؛ لا تولّد مفتاحًا جديدًا للنتيجة المجهولة. المزود الافتراضي disabled حتى تهيئته.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/vendor/payments` |
| `POST` | `/api/v1/vendor/payments` |
| `GET` | `/api/v1/vendor/payments/{payment}` |

## `GET /api/v1/vendor/payments`

Returns only payments owned by the caller, newest first.

Requires a bearer token for the Vendor actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `page` | query | لا | `{"type": "integer", "default": 1, "minimum": 1}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/vendor/payments" \
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
    "items": [
      {
        "id": 42,
        "uuid": "11111111-1111-4111-8111-111111111111",
        "provider": "stripe",
        "amount": 1500,
        "currency": "EGP",
        "status": "pending",
        "refunded_amount": 0,
        "client_secret": "pi_example_secret_REPLACE_FROM_RESPONSE",
        "created_at": "2026-10-11T10:00:00+00:00"
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 25,
      "total": 1
    }
  }
}
```

## `POST /api/v1/vendor/payments`

Creates or replays an idempotent payment intent. Supply Idempotency-Key in the header. Provider defaults to the configured gateway; payment processing must be configured by the operator. For Stripe, confirm client_secret using its SDK. For MyFatoorah, redirect to payment_url. Wait for a verified webhook or reconciliation to settle the payment.

Requires a bearer token for the Vendor actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Additional rate limit: 5 requests/minute per IP.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `amount` | integer | نعم | min: 1; max: 99999999 Positive integer in the currency’s smallest unit (100 = 1.00 for EGP/USD; 1000 = 1.000 for KWD/BHD/OMR/JOD). |
| `currency` | string | نعم | القيم: ["EGP", "USD", "SAR", "AED", "GBP", "CAD", "KWD", "BHD", "OMR", "JOD", "QAR"]  |
| `provider` | string | اختياري/شرطي | القيم: ["stripe", "myfatoorah"]; maxLength: 40 May be omitted only when an enabled default gateway is configured. |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/vendor/payments" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $VENDOR_TOKEN" \
  -H 'Idempotency-Key: v1-vendor-payments-store-001' \
  -H 'Content-Type: application/json' \
  --data '{"amount":1500,"currency":"EGP","provider":"stripe"}'
```

### مثال الاستجابة — HTTP 201

```json
{
  "success": true,
  "message": "The payment has been created.",
  "data": {
    "id": 42,
    "uuid": "11111111-1111-4111-8111-111111111111",
    "provider": "stripe",
    "amount": 1500,
    "currency": "EGP",
    "status": "pending",
    "refunded_amount": 0,
    "client_secret": "pi_example_secret_REPLACE_FROM_RESPONSE",
    "created_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `GET /api/v1/vendor/payments/{payment}`

Returns an owned payment. The client_secret field is present only for the owner while payment status is pending or processing.

Requires a bearer token for the Vendor actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `payment` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/vendor/payments/42" \
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
    "id": 42,
    "uuid": "11111111-1111-4111-8111-111111111111",
    "provider": "stripe",
    "amount": 1500,
    "currency": "EGP",
    "status": "pending",
    "refunded_amount": 0,
    "client_secret": "pi_example_secret_REPLACE_FROM_RESPONSE",
    "created_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [PaymentController.php](../../app/Http/Controllers/Shared/PaymentController.php)
