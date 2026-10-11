# Client — المحفظة وسجل الحركات

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اقرأ قائمة محافظ الحساب حسب العملة.
2. استخدم id الحقيقي للمحفظة لعرض transactions مع page.
3. إنشاء دفعة لا يزيد الرصيد فورًا؛ انتظر تسوية المزود ثم حدّث القائمة والحركات.

كل المبالغ integer minor units. EGP: 1500=15.00، KWD: 1500=1.500. السجل append-only ولا يوجد endpoint لتعديل الرصيد مباشرةً من Client/Vendor.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/client/wallet` |
| `GET` | `/api/v1/client/wallet/{wallet}/transactions` |

## `GET /api/v1/client/wallet`

Returns an unpaginated array of wallets ordered by currency. Balances are integers in minor units.

Requires a bearer token for the Client actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

[دليل الفلاتر والترتيب](../Shared/list-filtering.md)

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `sort` | query | لا | `{"type":"string","maxLength":100}` |
| `sort_type` | query | لا | `{"type":"string","enum":["asc","desc"]}` |
| `currency` | query | لا | `{"type":"string"}` |

Allowed fields: id, currency, balance. Up to three comma-separated fields; - means descending. Default: currency. A unique id tie-breaker is appended. Nullable values sort last.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/client/wallet" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $CLIENT_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 7,
      "currency": "EGP",
      "balance": 1500,
      "is_locked": false
    }
  ]
}
```

## `GET /api/v1/client/wallet/{wallet}/transactions`

Returns transactions for an owned wallet, newest first. Includes the balance after each transaction and the ledger reason.

Requires a bearer token for the Client actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

[قواعد الفلترة والترتيب والأمثلة](../Shared/list-filtering.md)

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `wallet` | path | نعم | `{"type":"integer"}` |
| `sort` | query | لا | `{"type":"string","maxLength":100}` |
| `sort_type` | query | لا | `{"type":"string","enum":["asc","desc"]}` |
| `page` | query | لا | `{"type":"integer","default":1,"minimum":1}` |
| `per_page` | query | لا | `{"type":"integer","default":25,"minimum":1,"maximum":100}` |
| `direction` | query | لا | `{"type":"string","enum":["credit","debit"]}` |
| `reason` | query | لا | `{"type":"string","maxLength":255}` |
| `created_from` | query | لا | `{"type":"string","format":"date"}` |
| `created_to` | query | لا | `{"type":"string","format":"date"}` |
| `amount_min` | query | لا | `{"type":"integer","minimum":0,"maximum":9000000000000000}` |
| `amount_max` | query | لا | `{"type":"integer","minimum":0,"maximum":9000000000000000}` |

Allowed fields: id, created_at, amount. Up to three comma-separated fields; - means descending. Default: -id. A unique id tie-breaker is appended. Nullable values sort last.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/client/wallet/7/transactions" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $CLIENT_TOKEN"
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "items": [
      {
        "id": 91,
        "direction": "credit",
        "amount": 1500,
        "balance_after": 1500,
        "reason": "payment:42",
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

- [WalletController.php](../../app/Http/Controllers/Shared/WalletController.php)
