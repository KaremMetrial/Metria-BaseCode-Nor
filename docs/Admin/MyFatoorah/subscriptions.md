# Admin — MyFatoorah — الاشتراكات المتكررة

[فهرس التوثيق](../../README.md) · [عقد HTTP والأخطاء](../../Shared/http-contract.md) · [كل المسارات](../../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. احصل على موافقة العميل واحفظ consent_reference في نظامك.
2. أنشئ الاشتراك ضمن recurring المفعّلة لدى التاجر.
3. تابع الاشتراكات ثم ألغها عند الطلب. retry مخصص لمحاولة اشتراك فاشلة وفق حالة المزود.

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/admin/integrations/myfatoorah/subscriptions/create` |
| `GET` | `/api/v1/admin/integrations/myfatoorah/subscriptions/list` |
| `POST` | `/api/v1/admin/integrations/myfatoorah/subscriptions/cancel` |
| `POST` | `/api/v1/admin/integrations/myfatoorah/subscriptions/retry` |

## `POST /api/v1/admin/integrations/myfatoorah/subscriptions/create`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `recurring`. [Official API contract](https://docs.myfatoorah.com/reference/execute-payment). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

On 503, check the operation history. Replaying an uncertain operation returns 409 RECONCILIATION_REQUIRED without another provider mutation.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `InvoiceValue` | number | نعم |   |
| `PaymentMethodId` | integer | نعم |   |
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
| `RecurringModel` | object | نعم |   |
| `RecurringModel.RecurringType` | string | نعم | القيم: ["Custom", "Daily", "Weekly", "Monthly"]  |
| `RecurringModel.IntervalDays` | integer | اختياري/شرطي | min: 1; max: 180  |
| `RecurringModel.Iteration` | integer | نعم |   |
| `RecurringModel.RetryCount` | integer | اختياري/شرطي | min: 0; max: 5  |
| `ProcessingDetails` | object | اختياري/شرطي |   |
| `ProcessingDetails.AutoCapture` | boolean | اختياري/شرطي |   |
| `ProcessingDetails.Bypass3DS` | boolean | اختياري/شرطي |   |
| `WebhookUrl` | string | اختياري/شرطي |   |
| `customer_id` | integer | اختياري/شرطي | min: 1  |
| `consent_reference` | string | نعم | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/integrations/myfatoorah/subscriptions/create" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Idempotency-Key: myfatoorah-subscriptions_create-001' \
  -H 'Content-Type: application/json' \
  --data '{"InvoiceValue":10,"PaymentMethodId":2,"RecurringModel":{"RecurringType":"Monthly","Iteration":12},"consent_reference":"signed-contract-17"}'
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
      "InvoiceId": 12,
      "RecurringId": "RECUR1"
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## `GET /api/v1/admin/integrations/myfatoorah/subscriptions/list`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `recurring`. [Official API contract](https://docs.myfatoorah.com/reference/get-recurring-payment). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `customer_id` | query | لا | `{"type": "integer", "minimum": 1}` |
| `consent_reference` | query | لا | `{"type": "string", "maxLength": 255}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/integrations/myfatoorah/subscriptions/list" \
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
      "RecurringPayment": [
        {
          "RecurringId": "RECUR1",
          "RecurringStatus": "Active"
        }
      ]
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## `POST /api/v1/admin/integrations/myfatoorah/subscriptions/cancel`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `recurring`. [Official API contract](https://docs.myfatoorah.com/reference/cancel-recurring-payment). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

On 503, check the operation history. Replaying an uncertain operation returns 409 RECONCILIATION_REQUIRED without another provider mutation.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `recurringId` | string | نعم |   |
| `customer_id` | integer | اختياري/شرطي | min: 1  |
| `consent_reference` | string | اختياري/شرطي | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/integrations/myfatoorah/subscriptions/cancel" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Idempotency-Key: myfatoorah-subscriptions_cancel-001' \
  -H 'Content-Type: application/json' \
  --data '{"recurringId":"RECUR1"}'
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
      "items": []
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## `POST /api/v1/admin/integrations/myfatoorah/subscriptions/retry`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `recurring`. [Official API contract](https://docs.myfatoorah.com/reference/resume-recurring-payment). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

On 503, check the operation history. Replaying an uncertain operation returns 409 RECONCILIATION_REQUIRED without another provider mutation.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `recurringId` | string | نعم |   |
| `customer_id` | integer | اختياري/شرطي | min: 1  |
| `consent_reference` | string | اختياري/شرطي | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/integrations/myfatoorah/subscriptions/retry" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Idempotency-Key: myfatoorah-subscriptions_retry-001' \
  -H 'Content-Type: application/json' \
  --data '{"recurringId":"RECUR1"}'
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
      "items": []
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [MyFatoorahController.php](../../../app/Http/Controllers/Admin/MyFatoorahController.php)
