# Admin — الدفع ومتابعة حالته

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اختر العملة والمبلغ بوحدتها الصغرى وحدد المزود المهيأ.
2. أنشئ الدفعة بـIdempotency-Key فريد للعملية.
3. Stripe: استخدم client_secret مع SDK؛ MyFatoorah: افتح payment_url.
4. عند عودة المستخدم اقرأ payment؛ redirect وحده ليس إثبات دفع.
5. انتظر webhook موقّعًا أو reconciliation ثم حدّث المحفظة.

المالك وحده يرى client_secret أثناء pending/processing. أعد المفتاح والطلب نفسهما بعد timeout/503؛ لا تولّد مفتاحًا جديدًا للنتيجة المجهولة. المزود الافتراضي disabled حتى تهيئته.

هذه الصفحة تعرض القراءة الإدارية لكل الحسابات فقط؛ إنشاء دفعة محفظة يتم من Client/Vendor. [الاستردادات](refunds.md) منفصلة.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/payments` |

## `GET /api/v1/admin/payments`

Returns payments across all accounts, newest first. Provider client secrets are only exposed to the payment owner when its status permits.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `payments.read`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `page` | query | لا | `{"type": "integer", "default": 1, "minimum": 1}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/payments" \
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
    "items": [
      {
        "id": 42,
        "uuid": "11111111-1111-4111-8111-111111111111",
        "provider": "stripe",
        "amount": 1500,
        "currency": "EGP",
        "status": "paid",
        "refunded_amount": 0,
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

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [FinanceController.php](../../app/Http/Controllers/Admin/FinanceController.php)
