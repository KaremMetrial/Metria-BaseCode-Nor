# Admin — MyFatoorah — الإمكانات وسجل العمليات والتسوية

[فهرس التوثيق](../../README.md) · [عقد HTTP والأخطاء](../../Shared/http-contract.md) · [كل المسارات](../../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

اقرأ capabilities، ثم سجل operations وentities. needs_refresh يعني أن الحدث الوارد أشار إلى تغير عند المزود. افحص المصدر قبل resolution؛ التسوية اليدوية توثيق قرار مدعوم بدليل، ولا تنشئ دفعًا أو حركة محفظة.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/integrations/myfatoorah/capabilities` |
| `GET` | `/api/v1/admin/integrations/myfatoorah/operations` |
| `GET` | `/api/v1/admin/integrations/myfatoorah/operations/{operationRecord}` |
| `PATCH` | `/api/v1/admin/integrations/myfatoorah/operations/{operationRecord}/resolution` |
| `GET` | `/api/v1/admin/integrations/myfatoorah/entities` |
| `GET` | `/api/v1/admin/integrations/myfatoorah/entities/{entity}` |

## `GET /api/v1/admin/integrations/myfatoorah/capabilities`

Lists implemented workflows and local feature switches. Enabled means configured locally; availability still depends on merchant-country activation and API-key permissions.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/integrations/myfatoorah/capabilities" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

### مثال الاستجابة — HTTP 200

مقتطف من قائمة الإمكانات؛ الرد الفعلي يسرد جميع العمليات المعرفة في الكتالوج.

```json
{
  "success": true,
  "message": null,
  "data": {
    "operations": [
      {
        "name": "invoices.create",
        "feature": "payments",
        "enabled": true,
        "method": "POST",
        "path": "/api/v1/admin/integrations/myfatoorah/invoices/create",
        "idempotency_required": true,
        "documentation": "https://docs.myfatoorah.com/reference/create-payment"
      }
    ]
  }
}
```

## `GET /api/v1/admin/integrations/myfatoorah/operations`

Returns the administrative operation history, including uncertain outcomes needing review. Request bodies and credentials are never returned.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

### Path / Query

[قواعد الفلترة والترتيب والأمثلة](../../Shared/list-filtering.md)

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `sort` | query | لا | `{"type":"string","maxLength":100}` |
| `sort_type` | query | لا | `{"type":"string","enum":["asc","desc"]}` |
| `page` | query | لا | `{"type":"integer","default":1,"minimum":1}` |
| `per_page` | query | لا | `{"type":"integer","default":25,"minimum":1,"maximum":100}` |
| `operation` | query | لا | `{"type":"string","maxLength":100}` |
| `status` | query | لا | `{"anyOf":[{"type":"string","enum":["ready","sending","succeeded","rejected","uncertain","confirmed_succeeded","confirmed_failed"]},{"type":"array","items":{"type":"string","enum":["ready","sending","succeeded","rejected","uncertain","confirmed_succeeded","confirmed_failed"]},"minItems":1,"maxItems":20}]}` |
| `customer_id` | query | لا | `{"type":"integer","minimum":1}` |
| `actor_id` | query | لا | `{"type":"integer","minimum":1}` |
| `created_from` | query | لا | `{"type":"string","format":"date"}` |
| `created_to` | query | لا | `{"type":"string","format":"date"}` |

Allowed fields: id, created_at, updated_at. Up to three comma-separated fields; - means descending. Default: -id. A unique id tie-breaker is appended. Nullable values sort last.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/integrations/myfatoorah/operations" \
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
        "id": 15,
        "uuid": "11111111-1111-4111-8111-111111111111",
        "actor_id": 1,
        "customer_id": 42,
        "operation": "invoices.create",
        "status": "succeeded",
        "result": {
          "InvoiceId": 12345
        },
        "created_at": "2026-10-11T10:00:00+00:00",
        "updated_at": "2026-10-11T10:00:00+00:00"
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

## `GET /api/v1/admin/integrations/myfatoorah/operations/{operationRecord}`

Retrieves the durable result of a workflow by local operation ID. A sending or uncertain operation must be checked against provider status before any replacement operation.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `operationRecord` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/integrations/myfatoorah/operations/15" \
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
    "id": 15,
    "uuid": "11111111-1111-4111-8111-111111111111",
    "actor_id": 1,
    "customer_id": 42,
    "operation": "invoices.create",
    "status": "succeeded",
    "result": {
      "InvoiceId": 12345
    },
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `PATCH /api/v1/admin/integrations/myfatoorah/operations/{operationRecord}/resolution`

After checking the merchant portal or provider support, an authorized administrator records the confirmed outcome of an uncertain operation. This is an operator attestation, not automatic provider verification. It never submits another provider request or changes a wallet. The original idempotency key remains permanently consumed.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `operationRecord` | path | نعم | `{"type": "integer"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `outcome` | string | نعم | القيم: ["confirmed_succeeded", "confirmed_failed"]  |
| `provider_reference` | string/null | اختياري/شرطي | maxLength: 255  |
| `evidence` | string | نعم | minLength: 10; maxLength: 2000  |

### مثال الطلب

```sh
curl --fail-with-body -X PATCH "$API_BASE/admin/integrations/myfatoorah/operations/15/resolution" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"outcome":"confirmed_succeeded","provider_reference":"12345","evidence":"Provider portal confirms invoice 12345; checked by the operator."}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 15,
    "uuid": "11111111-1111-4111-8111-111111111111",
    "actor_id": 1,
    "customer_id": 42,
    "operation": "invoices.create",
    "status": "confirmed_succeeded",
    "result": {
      "manual_resolution": {
        "outcome": "confirmed_succeeded",
        "provider_reference": "12345",
        "evidence": "Provider portal confirms invoice 12345; checked by the operator."
      },
      "resolved_by": 1
    },
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `GET /api/v1/admin/integrations/myfatoorah/entities`

Lists invoices, subscriptions, suppliers, shipments, sessions, refunds, transfers, settlements and disputes. needs_refresh indicates a signed webhook or command requires a fresh status inquiry. This is an administrative view and may contain sensitive customer or bank data.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

### Path / Query

[قواعد الفلترة والترتيب والأمثلة](../../Shared/list-filtering.md)

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `sort` | query | لا | `{"type":"string","maxLength":100}` |
| `sort_type` | query | لا | `{"type":"string","enum":["asc","desc"]}` |
| `page` | query | لا | `{"type":"integer","default":1,"minimum":1}` |
| `per_page` | query | لا | `{"type":"integer","default":25,"minimum":1,"maximum":100}` |
| `search` | query | لا | `{"type":"string","minLength":1,"maxLength":100}` |
| `kind` | query | لا | `{"type":"string","maxLength":40}` |
| `status` | query | لا | `{"type":"string","maxLength":60}` |
| `reference` | query | لا | `{"type":"string","maxLength":255}` |
| `customer_id` | query | لا | `{"type":"integer","minimum":1}` |
| `needs_refresh` | query | لا | `{"type":"boolean"}` |
| `created_from` | query | لا | `{"type":"string","format":"date"}` |
| `created_to` | query | لا | `{"type":"string","format":"date"}` |
| `synced_from` | query | لا | `{"type":"string","format":"date"}` |
| `synced_to` | query | لا | `{"type":"string","format":"date"}` |

Allowed fields: id, created_at, updated_at, synced_at. Up to three comma-separated fields; - means descending. Default: -id. A unique id tie-breaker is appended. Nullable values sort last.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/integrations/myfatoorah/entities" \
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
        "id": 10,
        "kind": "invoice",
        "reference": "12345",
        "customer_id": 42,
        "status": "PAID",
        "needs_refresh": true,
        "snapshot": {
          "InvoiceId": 12345,
          "Status": "PAID"
        },
        "last_event": null,
        "synced_at": null,
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

## `GET /api/v1/admin/integrations/myfatoorah/entities/{entity}`

Returns its latest provider snapshot and signed webhook fields. Webhook hints do not overwrite authoritative status or change application wallet balances.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `myfatoorah.manage`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `entity` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/integrations/myfatoorah/entities/10" \
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
    "id": 10,
    "kind": "invoice",
    "reference": "12345",
    "customer_id": 42,
    "status": "PAID",
    "needs_refresh": true,
    "snapshot": {
      "InvoiceId": 12345
    },
    "last_event": null,
    "synced_at": null,
    "created_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## حدود التسوية اليدوية

resolution مسموح فقط لسجل uncertain أوsending مرّ على updated_at أكثر من خمس دقائق. اختر confirmed_succeeded أوconfirmed_failed؛ النجاح يتطلب provider_reference، وevidence بطول 10–2000 حرف يوثق الفحص. السيرفر يسجل إقرار المشغل ولا يتحقق آليًا من الدليل في لوحة المزود. المفتاح القديم يظل مستهلكًا. لا تطبق مثال resolution على العملية الناجحة في مثال القراءة؛ يفترض هذا المثال عملية غير محسومة.

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [MyFatoorahController.php](../../../app/Http/Controllers/Admin/MyFatoorahController.php)
