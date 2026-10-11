# Admin — MyFatoorah — وسائل الدفع والتنفيذ والتحصيل

[فهرس التوثيق](../../README.md) · [عقد HTTP والأخطاء](../../Shared/http-contract.md) · [كل المسارات](../../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اعرض وسائل الدفع المتاحة.
2. نفّذ الدفع باستخدام جلسة أو وسيلة مهيأة.
3. تحقق من payment عبر البوابة.
4. CAPTURE يتطلب Amount وميزة auth_capture؛ RELEASE يحرر التفويض.

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/admin/integrations/myfatoorah/payments/update` |
| `POST` | `/api/v1/admin/integrations/myfatoorah/payments/execute` |
| `GET` | `/api/v1/admin/integrations/myfatoorah/payments/get` |
| `GET` | `/api/v1/admin/integrations/myfatoorah/payment-methods/list` |

## `POST /api/v1/admin/integrations/myfatoorah/payments/update`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `auth_capture`. [Official API contract](https://docs.myfatoorah.com/reference/update-payment). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

On 503, check the operation history. Replaying an uncertain operation returns 409 RECONCILIATION_REQUIRED without another provider mutation.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `OperationType` | string | نعم | القيم: ["CAPTURE", "RELEASE"]  |
| `Amount` | number | اختياري/شرطي |   |
| `paymentId` | string | نعم |   |
| `customer_id` | integer | اختياري/شرطي | min: 1  |
| `consent_reference` | string | اختياري/شرطي | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/integrations/myfatoorah/payments/update" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Idempotency-Key: myfatoorah-payments_update-001' \
  -H 'Content-Type: application/json' \
  --data '{"paymentId":"payment-business","OperationType":"CAPTURE","Amount":10}'
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
      "Invoice": {
        "Id": "business-invoice"
      }
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## `POST /api/v1/admin/integrations/myfatoorah/payments/execute`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `payments`. [Official API contract](https://docs.myfatoorah.com/reference/execute-payment). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

On 503, check the operation history. Replaying an uncertain operation returns 409 RECONCILIATION_REQUIRED without another provider mutation.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `InvoiceValue` | number | نعم |   |
| `PaymentMethodId` | integer | اختياري/شرطي |   |
| `SessionId` | string | اختياري/شرطي |   |
| `CustomerName` | string | اختياري/شرطي |   |
| `CallBackUrl` | string | اختياري/شرطي |   |
| `ErrorUrl` | string | اختياري/شرطي |   |
| `DisplayCurrencyIso` | string | اختياري/شرطي | القيم: ["KWD", "SAR", "BHD", "AED", "QAR", "OMR", "JOD", "EGP"]  |
| `MobileCountryCode` | string | اختياري/شرطي |   |
| `CustomerMobile` | string | اختياري/شرطي |   |
| `CustomerEmail` | string | اختياري/شرطي |   |
| `Language` | string | اختياري/شرطي | القيم: ["EN", "AR"]  |
| `CustomerReference` | string | اختياري/شرطي |   |
| `CustomerCivilId` | string | اختياري/شرطي |   |
| `UserDefinedField` | string | اختياري/شرطي |   |
| `CustomerAddress` | object | اختياري/شرطي |   |
| `CustomerAddress.Block` | string | اختياري/شرطي |   |
| `CustomerAddress.Street` | string | اختياري/شرطي |   |
| `CustomerAddress.HouseBuildingNo` | string | اختياري/شرطي |   |
| `CustomerAddress.Address` | string | اختياري/شرطي |   |
| `CustomerAddress.AddressInstructions` | string | اختياري/شرطي |   |
| `ExpiryDate` | string | اختياري/شرطي | الصيغة: "date-time"  |
| `InvoiceItems` | array | اختياري/شرطي |   |
| `InvoiceItems[].ItemName` | string | عند إرسال الأب |   |
| `InvoiceItems[].Quantity` | integer | عند إرسال الأب |   |
| `InvoiceItems[].UnitPrice` | number | عند إرسال الأب |   |
| `InvoiceItems[].Weight` | number | اختياري/شرطي |   |
| `InvoiceItems[].Width` | number | اختياري/شرطي |   |
| `InvoiceItems[].Height` | number | اختياري/شرطي |   |
| `InvoiceItems[].Depth` | number | اختياري/شرطي |   |
| `InvoiceItems[].Description` | string | اختياري/شرطي | maxLength: 500  |
| `ShippingMethod` | integer | اختياري/شرطي | القيم: [1, 2]  |
| `ShippingConsignee` | object | اختياري/شرطي |   |
| `ShippingConsignee.PersonName` | string | عند إرسال الأب |   |
| `ShippingConsignee.Mobile` | string | عند إرسال الأب |   |
| `ShippingConsignee.EmailAddress` | string | اختياري/شرطي |   |
| `ShippingConsignee.LineAddress` | string | عند إرسال الأب |   |
| `ShippingConsignee.CityName` | string | عند إرسال الأب |   |
| `ShippingConsignee.PostalCode` | string | اختياري/شرطي |   |
| `ShippingConsignee.CountryCode` | string | عند إرسال الأب |   |
| `Suppliers` | array | اختياري/شرطي |   |
| `Suppliers[].SupplierCode` | integer | عند إرسال الأب |   |
| `Suppliers[].ProposedShare` | number | اختياري/شرطي |   |
| `Suppliers[].InvoiceShare` | number | عند إرسال الأب |   |
| `RecurringModel` | object | اختياري/شرطي |   |
| `RecurringModel.RecurringType` | string | اختياري/شرطي | القيم: ["Custom", "Daily", "Weekly", "Monthly"]  |
| `RecurringModel.IntervalDays` | integer | اختياري/شرطي |   |
| `RecurringModel.Iteration` | integer | اختياري/شرطي |   |
| `RecurringModel.RetryCount` | integer | اختياري/شرطي |   |
| `ProcessingDetails` | object | اختياري/شرطي |   |
| `ProcessingDetails.AutoCapture` | boolean | اختياري/شرطي |   |
| `ProcessingDetails.Bypass3DS` | boolean | اختياري/شرطي |   |
| `WebhookUrl` | string | اختياري/شرطي |   |
| `customer_id` | integer | اختياري/شرطي | min: 1  |
| `consent_reference` | string | اختياري/شرطي | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/integrations/myfatoorah/payments/execute" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Idempotency-Key: myfatoorah-payments_execute-001' \
  -H 'Content-Type: application/json' \
  --data '{"SessionId":"session1","InvoiceValue":10}'
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
      "InvoiceId": 77
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## `GET /api/v1/admin/integrations/myfatoorah/payments/get`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `payments`. [Official API contract](https://docs.myfatoorah.com/reference/get-payment-details). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `paymentId` | query | نعم | `{"type": "string", "examples": ["100"]}` |
| `customer_id` | query | لا | `{"type": "integer", "minimum": 1}` |
| `consent_reference` | query | لا | `{"type": "string", "maxLength": 255}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/integrations/myfatoorah/payments/get" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  --get \
  --data-urlencode 'paymentId=100'
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

## `GET /api/v1/admin/integrations/myfatoorah/payment-methods/list`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `payments`. [Official API contract](https://docs.myfatoorah.com/reference/get-payment-methods). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `customer_id` | query | لا | `{"type": "integer", "minimum": 1}` |
| `consent_reference` | query | لا | `{"type": "string", "maxLength": 255}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/integrations/myfatoorah/payment-methods/list" \
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
    "operation_id": null,
    "status": "succeeded",
    "result": {
      "PaymentMethods": [
        {
          "ApiName": "KNET"
        }
      ]
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [MyFatoorahController.php](../../../app/Http/Controllers/Admin/MyFatoorahController.php)
