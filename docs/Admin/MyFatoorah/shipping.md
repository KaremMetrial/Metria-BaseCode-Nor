# Admin — MyFatoorah — الشحن

[فهرس التوثيق](../../README.md) · [عقد HTTP والأخطاء](../../Shared/http-contract.md) · [كل المسارات](../../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

اعرض الدول والمدن، اطلب quote، ثم أنشئ عملية الشحن وحدّث الطلب أو اطلب pickup حسب استجابة المزود. لا تنفّذ الإنشاء قبل التحقق من العنوان وتفعيل shipping.

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/admin/integrations/myfatoorah/shipping/create` |
| `GET` | `/api/v1/admin/integrations/myfatoorah/shipping/countries` |
| `GET` | `/api/v1/admin/integrations/myfatoorah/shipping/cities` |
| `POST` | `/api/v1/admin/integrations/myfatoorah/shipping/quote` |
| `GET` | `/api/v1/admin/integrations/myfatoorah/shipping/orders` |
| `POST` | `/api/v1/admin/integrations/myfatoorah/shipping/update` |
| `POST` | `/api/v1/admin/integrations/myfatoorah/shipping/pickup` |

## `POST /api/v1/admin/integrations/myfatoorah/shipping/create`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `shipping`. [Official API contract](https://docs.myfatoorah.com/reference/send-payment). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

On 503, check the operation history. Replaying an uncertain operation returns 409 RECONCILIATION_REQUIRED without another provider mutation.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `CustomerName` | string | نعم |   |
| `InvoiceValue` | number | نعم |   |
| `NotificationOption` | string | نعم | القيم: ["EML", "SMS", "LNK", "ALL"]  |
| `DisplayCurrencyIso` | string | اختياري/شرطي | القيم: ["KWD", "SAR", "BHD", "AED", "QAR", "OMR", "JOD", "EGP"]  |
| `MobileCountryCode` | string | اختياري/شرطي |   |
| `CustomerMobile` | string | اختياري/شرطي |   |
| `CustomerEmail` | string | اختياري/شرطي |   |
| `Language` | string | اختياري/شرطي | القيم: ["EN", "AR"]  |
| `CustomerReference` | string | اختياري/شرطي |   |
| `CustomerCivilId` | string | اختياري/شرطي |   |
| `UserDefinedField` | string | اختياري/شرطي |   |
| `CallBackUrl` | string | اختياري/شرطي |   |
| `ErrorUrl` | string | اختياري/شرطي |   |
| `CustomerAddress` | object | اختياري/شرطي |   |
| `CustomerAddress.Block` | string | اختياري/شرطي |   |
| `CustomerAddress.Street` | string | اختياري/شرطي |   |
| `CustomerAddress.HouseBuildingNo` | string | اختياري/شرطي |   |
| `CustomerAddress.AddressInstructions` | string | اختياري/شرطي |   |
| `ExpiryDate` | string | اختياري/شرطي | الصيغة: "date-time"  |
| `InvoiceItems` | array | نعم |   |
| `InvoiceItems[].ItemName` | string | عند إرسال الأب |   |
| `InvoiceItems[].Quantity` | integer | عند إرسال الأب |   |
| `InvoiceItems[].UnitPrice` | number | عند إرسال الأب |   |
| `InvoiceItems[].Weight` | number | عند إرسال الأب |   |
| `InvoiceItems[].Width` | number | عند إرسال الأب |   |
| `InvoiceItems[].Height` | number | عند إرسال الأب |   |
| `InvoiceItems[].Depth` | number | عند إرسال الأب |   |
| `InvoiceItems[].Description` | string | اختياري/شرطي | maxLength: 500  |
| `ShippingMethod` | integer | نعم | القيم: [1, 2]  |
| `ShippingConsignee` | object | نعم |   |
| `ShippingConsignee.PersonName` | string | نعم |   |
| `ShippingConsignee.Mobile` | string | نعم |   |
| `ShippingConsignee.EmailAddress` | string | اختياري/شرطي | الصيغة: "email"  |
| `ShippingConsignee.LineAddress` | string | نعم |   |
| `ShippingConsignee.CityName` | string | نعم |   |
| `ShippingConsignee.PostalCode` | string | اختياري/شرطي |   |
| `ShippingConsignee.CountryCode` | string | نعم |   |
| `Suppliers` | array | اختياري/شرطي |   |
| `Suppliers[].SupplierCode` | integer | عند إرسال الأب |   |
| `Suppliers[].ProposedShare` | number | اختياري/شرطي |   |
| `Suppliers[].InvoiceShare` | number | عند إرسال الأب |   |
| `ProcessingDetails` | object | اختياري/شرطي |   |
| `ProcessingDetails.AutoCapture` | boolean | اختياري/شرطي |   |
| `InvoicePaymentMethods` | array | اختياري/شرطي |   |
| `WebhookUrl` | string | اختياري/شرطي |   |
| `customer_id` | integer | اختياري/شرطي | min: 1  |
| `consent_reference` | string | اختياري/شرطي | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/integrations/myfatoorah/shipping/create" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Idempotency-Key: myfatoorah-shipping_create-001' \
  -H 'Content-Type: application/json' \
  --data '{"CustomerName":"Test customer","InvoiceValue":10,"NotificationOption":"LNK","ShippingMethod":1,"ShippingConsignee":{"PersonName":"Test customer","Mobile":"96512345678","LineAddress":"Street 1","CityName":"Kuwait","CountryCode":"KW"},"InvoiceItems":[{"ItemName":"Book","Quantity":1,"UnitPrice":10,"Weight":1,"Width":10,"Height":10,"Depth":10}]}'
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
      "InvoiceId": 89
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## `GET /api/v1/admin/integrations/myfatoorah/shipping/countries`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `shipping`. [Official API contract](https://docs.myfatoorah.com/reference/get-countries). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `customer_id` | query | لا | `{"type": "integer", "minimum": 1}` |
| `consent_reference` | query | لا | `{"type": "string", "maxLength": 255}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/integrations/myfatoorah/shipping/countries" \
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
      "items": [
        {
          "CountryCode": "KW"
        }
      ]
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## `GET /api/v1/admin/integrations/myfatoorah/shipping/cities`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `shipping`. [Official API contract](https://docs.myfatoorah.com/reference/get-cities). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `shippingMethod` | query | نعم | `{"type": "integer", "enum": [1, 2], "examples": [1]}` |
| `countryCode` | query | نعم | `{"type": "string", "examples": ["KW"]}` |
| `searchValue` | query | لا | `{"type": "string"}` |
| `customer_id` | query | لا | `{"type": "integer", "minimum": 1}` |
| `consent_reference` | query | لا | `{"type": "string", "maxLength": 255}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/integrations/myfatoorah/shipping/cities" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  --get \
  --data-urlencode 'shippingMethod=1' \
  --data-urlencode 'countryCode=KW'
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
      "items": []
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## `POST /api/v1/admin/integrations/myfatoorah/shipping/quote`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `shipping`. [Official API contract](https://docs.myfatoorah.com/reference/calculate-shipping-charge). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `ShippingMethod` | integer | نعم | القيم: [1, 2]  |
| `Items` | array | نعم |   |
| `Items[].ProductName` | string | اختياري/شرطي |   |
| `Items[].Description` | string | اختياري/شرطي |   |
| `Items[].Weight` | number | عند إرسال الأب | min: 0; max: 100  |
| `Items[].Width` | number | عند إرسال الأب | min: 0; max: 200  |
| `Items[].Height` | number | عند إرسال الأب | min: 0; max: 160  |
| `Items[].Depth` | number | عند إرسال الأب | min: 0; max: 200  |
| `Items[].Quantity` | integer | عند إرسال الأب |   |
| `Items[].UnitPrice` | number | عند إرسال الأب |   |
| `CityName` | string | نعم |   |
| `PostalCode` | string | اختياري/شرطي |   |
| `CountryCode` | string | نعم |   |
| `customer_id` | integer | اختياري/شرطي | min: 1  |
| `consent_reference` | string | اختياري/شرطي | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/integrations/myfatoorah/shipping/quote" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"ShippingMethod":1,"CountryCode":"KW","CityName":"Kuwait","Items":[{"Weight":1,"Width":10,"Height":10,"Depth":10,"Quantity":1,"UnitPrice":10}]}'
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
      "Fees": 1.5
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## `GET /api/v1/admin/integrations/myfatoorah/shipping/orders`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `shipping`. [Official API contract](https://docs.myfatoorah.com/reference/get-shipping-order-list). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `ShippingMethod` | query | نعم | `{"type": "integer", "enum": [1, 2], "examples": [2]}` |
| `orderStatus` | query | نعم | `{"type": "integer", "enum": [0, 1, 2, 3, 4], "examples": [1]}` |
| `start` | query | لا | `{"type": "integer"}` |
| `length` | query | لا | `{"type": "integer"}` |
| `customer_id` | query | لا | `{"type": "integer", "minimum": 1}` |
| `consent_reference` | query | لا | `{"type": "string", "maxLength": 255}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/integrations/myfatoorah/shipping/orders" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  --get \
  --data-urlencode 'ShippingMethod=2' \
  --data-urlencode 'orderStatus=1'
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
      "ShippingOrder": [
        {
          "OrderNumber": 89,
          "OrderStatus": "Prepared"
        }
      ]
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## `POST /api/v1/admin/integrations/myfatoorah/shipping/update`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `shipping`. [Official API contract](https://docs.myfatoorah.com/reference/update-shipping-status). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

On 503, check the operation history. Replaying an uncertain operation returns 409 RECONCILIATION_REQUIRED without another provider mutation.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `ShippingMethod` | integer | نعم | القيم: [1, 2]  |
| `InvoiceNumbers` | array | نعم |   |
| `OrderStatusChangedTo` | integer | نعم | القيم: [0, 1, 3, 4]  |
| `customer_id` | integer | اختياري/شرطي | min: 1  |
| `consent_reference` | string | اختياري/شرطي | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/integrations/myfatoorah/shipping/update" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Idempotency-Key: myfatoorah-shipping_update-001' \
  -H 'Content-Type: application/json' \
  --data '{"ShippingMethod":1,"InvoiceNumbers":[89],"OrderStatusChangedTo":1}'
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
      "ShippingOrder": [
        {
          "OrderNumber": 89,
          "OrderStatus": "Prepared"
        }
      ]
    }
  }
}
```

`result` مثال من بيانات اختبارات التكامل في المشروع، وليس استجابة حية؛ الحقول الفعلية تعتمد على حساب المزود ونسخة API. التطبيق لا يفرض شكلًا ثابتًا لنتائج هذه العملية. راجع عقد المزود المرتبط في وصف العملية لمعاني الحقول. نتيجة array تُغلف في `{"items": [...]}` ونتيجة scalar/null في `{"value": ..., "message": ...}`. لا تعتبر succeeded هنا تسوية مالية أو قبول KYC نهائيًا.

## `POST /api/v1/admin/integrations/myfatoorah/shipping/pickup`

Executes the documented provider operation with validated inputs. Mutations require Idempotency-Key and persist an operation record. Uncertain outcomes require reconciliation; they are never blindly resubmitted. Provider amounts use decimal major units. These business invoices do not credit application wallets.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

Feature: `shipping`. [Official API contract](https://docs.myfatoorah.com/reference/request-pickup). Use provider field names with their original capitalization. Amounts here are decimal major units; wallet endpoints use integer minor units. Raw card numbers and CVVs are rejected. `customer_id` optionally associates a new business record with a local customer. `consent_reference` is required for subscriptions and saved-token charges.

On 503, check the operation history. Replaying an uncertain operation returns 409 RECONCILIATION_REQUIRED without another provider mutation.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `ShippingMethod` | integer | نعم | القيم: [1, 2]  |
| `customer_id` | integer | اختياري/شرطي | min: 1  |
| `consent_reference` | string | اختياري/شرطي | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/integrations/myfatoorah/shipping/pickup" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Idempotency-Key: myfatoorah-shipping_pickup-001' \
  -H 'Content-Type: application/json' \
  --data '{"ShippingMethod":1}'
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
      "items": [
        {
          "OrderNumber": 89,
          "OrderStatus": "RequestPickup"
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
