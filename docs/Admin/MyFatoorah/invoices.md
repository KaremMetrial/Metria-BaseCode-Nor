# Admin — MyFatoorah — الفواتير التجارية

[فهرس التوثيق](../../README.md) · [عقد HTTP والأخطاء](../../Shared/http-contract.md) · [كل المسارات](../../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. أنشئ فاتورة مع Order والمبلغ بعملة المزود.
2. افتح PaymentURL الراجع.
3. استعلم بالمعرف أو externalIdentifier.
4. لا تعد رجوع المتصفح أو نجاح إنشاء الفاتورة إثبات تحصيل.

مثال الإنشاء يتضمن Suppliers لتقسيم الفاتورة ويتطلب multivendor أيضًا. لفاتورة عادية احذف Suppliers وأرسل Order فقط وفق الخيارات المطلوبة لحسابك.

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/admin/integrations/myfatoorah/invoices/create` |
| `GET` | `/api/v1/admin/integrations/myfatoorah/invoices/get` |
| `GET` | `/api/v1/admin/integrations/myfatoorah/invoices/find` |

## `POST /api/v1/admin/integrations/myfatoorah/invoices/create`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `payments`. [Official API contract](https://docs.myfatoorah.com/reference/create-payment). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

On 503, check the operation history. Replaying an uncertain operation returns 409 RECONCILIATION_REQUIRED without another provider mutation.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `PaymentMethod` | string | اختياري/شرطي | القيم: ["CARD", "APPLE_PAY", "GOOGLE_PAY", "KNET"]  |
| `Order` | object | نعم |   |
| `Order.Amount` | number | نعم |   |
| `Order.Currency` | string | اختياري/شرطي | القيم: ["SAR", "BHD", "AED", "QAR", "OMR", "KWD", "JOD", "EGP"]  |
| `Order.ExternalIdentifier` | string | اختياري/شرطي |   |
| `SourceOfFund` | object | اختياري/شرطي |   |
| `SourceOfFund.SessionId` | string | اختياري/شرطي |   |
| `SourceOfFund.Token` | string | اختياري/شرطي |   |
| `SourceOfFund.Type` | string | اختياري/شرطي |   |
| `PaymentExpiry` | string | اختياري/شرطي | الصيغة: "date-time"  |
| `SaveCardOptions` | object | اختياري/شرطي |   |
| `SaveCardOptions.SaveToken` | boolean | اختياري/شرطي |   |
| `ThreeDS` | object | اختياري/شرطي |   |
| `ThreeDS.Enabled` | boolean | اختياري/شرطي |   |
| `ThreeDS.AuthenticationValue` | string | اختياري/شرطي |   |
| `ThreeDS.DirectoryServerTransactionId` | string | اختياري/شرطي |   |
| `ThreeDS.Eci` | string | اختياري/شرطي |   |
| `ThreeDS.ProtocolVersion` | string | اختياري/شرطي |   |
| `ThreeDS.TransactionStatus` | string | اختياري/شرطي |   |
| `NotificationOption` | string | اختياري/شرطي | القيم: ["EMAIL", "SMS", "ALL", "LINK"]  |
| `OperationType` | string | اختياري/شرطي | القيم: ["AUTHORIZE", "PAY"]  |
| `Suppliers` | array | اختياري/شرطي |   |
| `Suppliers[].SupplierCode` | integer | اختياري/شرطي |   |
| `Suppliers[].ProposedDepositShare` | number | اختياري/شرطي |   |
| `Suppliers[].InvoiceShare` | number | اختياري/شرطي |   |
| `Customer` | object | اختياري/شرطي |   |
| `Customer.Name` | string | اختياري/شرطي |   |
| `Customer.Mobile` | object | اختياري/شرطي |   |
| `Customer.Mobile.CountryCode` | string | اختياري/شرطي |   |
| `Customer.Mobile.Number` | string | اختياري/شرطي |   |
| `Customer.Email` | string | اختياري/شرطي |   |
| `Customer.Reference` | string | اختياري/شرطي |   |
| `Customer.CivilId` | string | اختياري/شرطي |   |
| `IntegrationUrls` | object | اختياري/شرطي |   |
| `IntegrationUrls.Redirection` | string | اختياري/شرطي |   |
| `IntegrationUrls.Webhook` | string | اختياري/شرطي |   |
| `Language` | string | اختياري/شرطي | القيم: ["EN", "AR"]  |
| `IpAddress` | string | اختياري/شرطي |   |
| `MetaData` | object | اختياري/شرطي |   |
| `MetaData.UDF1` | string | اختياري/شرطي |   |
| `MetaData.UDF2` | string | اختياري/شرطي |   |
| `MetaData.UDF3` | string | اختياري/شرطي |   |
| `MetaData.UDF4` | string | اختياري/شرطي |   |
| `MetaData.UDF5` | string | اختياري/شرطي |   |
| `DisplayPaymentMethods` | array | اختياري/شرطي |   |
| `NetworkTransactionId` | string | اختياري/شرطي |   |
| `customer_id` | integer | اختياري/شرطي | min: 1  |
| `consent_reference` | string | اختياري/شرطي | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/integrations/myfatoorah/invoices/create" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Idempotency-Key: myfatoorah-invoices_create-001' \
  -H 'Content-Type: application/json' \
  --data '{"Order":{"Amount":10,"Currency":"KWD"},"Suppliers":[{"SupplierCode":7,"InvoiceShare":8}]}'
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
      "InvoiceId": "invoice-business",
      "PaymentURL": "https://demo.myfatoorah.com/payment"
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## `GET /api/v1/admin/integrations/myfatoorah/invoices/get`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `payments`. [Official API contract](https://docs.myfatoorah.com/reference/get-invoice-by-invoiceid). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `InvoiceId` | query | نعم | `{"type": "string", "examples": ["100"]}` |
| `customer_id` | query | لا | `{"type": "integer", "minimum": 1}` |
| `consent_reference` | query | لا | `{"type": "string", "maxLength": 255}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/integrations/myfatoorah/invoices/get" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  --get \
  --data-urlencode 'InvoiceId=100'
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
      "Invoice": {
        "Id": "100",
        "Status": "PAID"
      }
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## `GET /api/v1/admin/integrations/myfatoorah/invoices/find`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `payments`. [Official API contract](https://docs.myfatoorah.com/reference/get-invoice-by-externalidentifier). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `externalIdentifier` | query | نعم | `{"type": "string", "examples": ["order-7"]}` |
| `customer_id` | query | لا | `{"type": "integer", "minimum": 1}` |
| `consent_reference` | query | لا | `{"type": "string", "maxLength": 255}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/integrations/myfatoorah/invoices/find" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  --get \
  --data-urlencode 'externalIdentifier=order-7'
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
      "Invoice": {
        "Id": "100"
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
