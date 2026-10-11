# Admin — MyFatoorah — استرداد المدفوعات

[فهرس التوثيق](../../README.md) · [عقد HTTP والأخطاء](../../Shared/http-contract.md) · [كل المسارات](../../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اقرأ حالة عملية البوابة وحدد PaymentId والمبلغ بعملة المزود.
2. أرسل refunds.create بمفتاح ثابت.
3. تابع refunds.get حتى التأكيد. هذا استرداد تجاري لا يغير محفظة التطبيق؛ لاسترداد شحن المحفظة استخدم Admin/refunds.

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/admin/integrations/myfatoorah/refunds/create` |
| `GET` | `/api/v1/admin/integrations/myfatoorah/refunds/get` |

## `POST /api/v1/admin/integrations/myfatoorah/refunds/create`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `payments`. [Official API contract](https://docs.myfatoorah.com/reference/create-refund). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

On 503, check the operation history. Replaying an uncertain operation returns 409 RECONCILIATION_REQUIRED without another provider mutation.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `PaymentId` | string | نعم |   |
| `Amount` | number | نعم |   |
| `ExternalIdentifier` | string | اختياري/شرطي |   |
| `SupplierRefundedAmount` | array | اختياري/شرطي |   |
| `SupplierRefundedAmount[].Code` | string | اختياري/شرطي |   |
| `SupplierRefundedAmount[].Amount` | number | اختياري/شرطي |   |
| `Comment` | string | اختياري/شرطي |   |
| `ServiceChargeOnCustomer` | boolean | اختياري/شرطي |   |
| `customer_id` | integer | اختياري/شرطي | min: 1  |
| `consent_reference` | string | اختياري/شرطي | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/integrations/myfatoorah/refunds/create" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Idempotency-Key: myfatoorah-refunds_create-001' \
  -H 'Content-Type: application/json' \
  --data '{"PaymentId":"payment-business","Amount":1}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "operation_id": "11111111-1111-4111-8111-111111111111",
    "status": "succeeded",
    "result": {
      "Refund": {
        "Id": "refund-business",
        "Status": "Pending"
      }
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## `GET /api/v1/admin/integrations/myfatoorah/refunds/get`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `payments`. [Official API contract](https://docs.myfatoorah.com/reference/get-refund-details). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `refundId` | query | نعم | `{"type": "string", "examples": ["refund1"]}` |
| `customer_id` | query | لا | `{"type": "integer", "minimum": 1}` |
| `consent_reference` | query | لا | `{"type": "string", "maxLength": 255}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/integrations/myfatoorah/refunds/get" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  --get \
  --data-urlencode 'refundId=refund1'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "operation_id": null,
    "status": "succeeded",
    "result": {
      "Refund": {
        "Id": "refund1",
        "Status": "Refunded"
      }
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [MyFatoorahController.php](../../../app/Http/Controllers/Admin/MyFatoorahController.php)
