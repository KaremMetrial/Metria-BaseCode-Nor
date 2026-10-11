# Admin — إدارة المحافظ والتسويات

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اعرض جميع المحافظ بصلاحية wallets.read.
2. لتحريك الرصيد، اختر المحفظة وحدد credit/debit ومبلغًا موجبًا وسببًا واضحًا.
3. استخدم Idempotency-Key ثابتًا للعملية وأعد نفس الطلب عند timeout.
4. تحقق من balance_after ولا تُعدّل ledger يدويًا.

يلزم wallets.adjust مع wallets.credit أو wallets.debit. finance-admin لا يحصل افتراضيًا على صلاحيات التسوية. المحفظة المقفلة أو الرصيد غير الكافي يمنعان العملية.

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/admin/wallets/{wallet}/adjustments` |
| `GET` | `/api/v1/admin/wallets` |

## `POST /api/v1/admin/wallets/{wallet}/adjustments`

Requires wallets.adjust and wallets.credit or wallets.debit according to direction. Include a reason and an Idempotency-Key header. The ledger is append-only; locked wallets and insufficient balances reject the operation.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `wallets.adjust`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `wallet` | path | نعم | `{"type": "integer"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `amount` | integer | نعم | min: 1; max: 99999999 Positive integer in the currency’s smallest unit (100 = 1.00 for EGP/USD; 1000 = 1.000 for KWD/BHD/OMR/JOD). |
| `direction` | string | نعم | القيم: ["credit", "debit"]  |
| `reason` | string | نعم | maxLength: 255  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/wallets/7/adjustments" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Idempotency-Key: v1-admin-wallet-adjust-001' \
  -H 'Content-Type: application/json' \
  --data '{"amount":500,"direction":"credit","reason":"Approved service compensation"}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": "The wallet has been adjusted.",
  "data": {
    "id": 91,
    "direction": "credit",
    "amount": 500,
    "balance_after": 2000,
    "reason": "Approved service compensation",
    "created_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `GET /api/v1/admin/wallets`

Returns wallets across all accounts, newest first.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `wallets.read`.

### Path / Query

[قواعد الفلترة والترتيب والأمثلة](../Shared/list-filtering.md)

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `sort` | query | لا | `{"type":"string","maxLength":100}` |
| `sort_type` | query | لا | `{"type":"string","enum":["asc","desc"]}` |
| `page` | query | لا | `{"type":"integer","default":1,"minimum":1}` |
| `per_page` | query | لا | `{"type":"integer","default":25,"minimum":1,"maximum":100}` |
| `currency` | query | لا | `{"type":"string"}` |
| `user_id` | query | لا | `{"type":"integer","minimum":1}` |
| `is_locked` | query | لا | `{"type":"boolean"}` |
| `balance_min` | query | لا | `{"type":"integer","minimum":0,"maximum":9000000000000000}` |
| `balance_max` | query | لا | `{"type":"integer","minimum":0,"maximum":9000000000000000}` |

Allowed fields: id, currency, balance. Up to three comma-separated fields; - means descending. Default: -id. A unique id tie-breaker is appended. Nullable values sort last.

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/wallets" \
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
        "id": 7,
        "currency": "EGP",
        "balance": 1500,
        "is_locked": false
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
- [WalletAdjustmentController.php](../../app/Http/Controllers/Admin/WalletAdjustmentController.php)
