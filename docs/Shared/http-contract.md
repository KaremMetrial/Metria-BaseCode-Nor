# عقد HTTP واللغة والأخطاء

[الفهرس](../README.md)

## Requests, localization and responses

Send JSON with `Content-Type: application/json`. API errors return JSON even when `Accept` is omitted. Unsupported explicit locales return 422 `UNSUPPORTED_LOCALE`.

| Header or query | Meaning |
| --- | --- |
| `Authorization: Bearer …` | Sanctum token for protected operations |
| `X-Locale: en` or `ar` | Requested language |
| `?locale=en` or `ar` | Language override with the highest priority |
| `Accept-Language` | Negotiated language when no explicit/user preference applies |
| `X-Request-ID` | Optional 8–64 characters from letters, digits, `.`, `_`, `-`; invalid/missing values are replaced |
| `Idempotency-Key` | Required for payment creation, refunds and wallet adjustments |

Language precedence: query override → `X-Locale` → authenticated user preference → `Accept-Language` → English. Regional browser languages such as `ar-EG` resolve to `ar`; language preferences with `q=0` are ignored. An unsupported explicit locale still returns 422; its message uses the next supported preference (header, saved user language, browser language, then English).

Responses include `Content-Language: en` or `ar` and an echoed or generated `X-Request-ID` for tracing. Human-readable errors, validation messages, field labels, and translated resource fields use the resolved language, including routing errors and unexpected server failures. Field keys, IDs, enum values, and error codes remain stable. Provider snapshots and customer-entered text are preserved as received.

For Arabic validation, send `X-Locale: ar`, for example on an empty admin login request:

```json
{
  "success": false,
  "code": "VALIDATION_FAILED",
  "message": "البيانات المُرسلة غير صحيحة.",
  "errors": {
    "email": ["حقل البريد الإلكتروني مطلوب."],
    "password": ["حقل كلمة المرور مطلوب."]
  }
}
```

Success:

```json
{"success":true,"message":null,"data":{"id":42}}
```

`data` may be an object, an array or `null`. Deletions and logout return HTTP 200 with `data: null`; they do not return an empty 204 response. Creation endpoints generally return 201 as shown in the reference.

Validation error:

```json
{
  "success": false,
  "code": "VALIDATION_FAILED",
  "message": "The given data was invalid.",
  "errors": {"amount": ["The amount field is required."]}
}
```

Human-readable messages are illustrative and localized. Use `code` for decisions. Errors without field details have `errors: {}`, not an array.

| HTTP | Meaning and examples |
| --- | --- |
| 400 | `INVALID_WEBHOOK`: invalid provider signature or payload |
| 401 | `UNAUTHENTICATED`, `INVALID_CREDENTIALS` |
| 403 | `FORBIDDEN`, `ACCOUNT_PENDING`, `ACCOUNT_DISABLED` |
| 404 | `NOT_FOUND`: absent, hidden or inaccessible owned resource |
| 405 | `METHOD_NOT_ALLOWED`: incorrect HTTP method |
| 409 | `RESOURCE_CONFLICT`, `RESOURCE_IN_USE`, `PHONE_ALREADY_EXISTS`, `IDEMPOTENCY_CONFLICT`, `RECONCILIATION_REQUIRED`, `PAYMENT_ALREADY_REFUNDED` |
| 413 | Webhook body exceeds 262144 bytes; current application code is `INTERNAL_ERROR` |
| 422 | Validation, locale, phone, OTP, hierarchy or financial business-rule failure |
| 429 | `RATE_LIMITED`, `OTP_COOLDOWN`, `OTP_ATTEMPTS_EXCEEDED` |
| 500 | `INTERNAL_ERROR`: unexpected failure; retain the request ID |
| 503 | `PROVIDER_UNAVAILABLE`: required service unavailable or uncertain financial result |

`app/Enums/ErrorCode.php` is the stable code registry. `app/Support/ApiResponse.php` owns status mappings. Each OpenAPI error response lists the codes for its status; the applicable code depends on the operation and failed rule.

## Lists and translated resources

Paginated endpoints return:

```json
{
  "success": true,
  "message": null,
  "data": {
    "items": [],
    "meta": {"current_page":1,"last_page":1,"per_page":25,"total":0}
  }
}
```

Paginated lists accept `page` (minimum 1) and `per_page` (1–100, default 25). Each resource has explicit filter and sort allowlists; see [list filtering](list-filtering.md). Location lists and the current user's wallet list retain plain arrays in `data`; support-message and notification sync use their existing streaming contracts.

Admin translation payloads are objects keyed by locale:

```json
{"translations":{"en":{"name":"Example"},"ar":{"name":"مثال"}}}
```

Creation requires the English name. Arabic is optional. PATCH permits omitted fields and locales, but every supplied locale object requires a name. Only `en` and `ar` keys are accepted. Country translations optionally contain `nationality`; categories optionally contain `description`. Public resources expose the selected language; authorized administrative resources include translations.


## 12. الأخطاء وحدود الطلبات

### أمثلة أخطاء

كود OTP غير صحيح — HTTP 422:

```json
{
  "success": false,
  "code": "INVALID_OTP",
  "message": "The verification code is invalid.",
  "errors": {}
}
```

عدم إرسال الاسم أثناء إكمال الحساب — HTTP 422 (رسائل الحقول تتبع اللغة):

```json
{
  "success": false,
  "code": "VALIDATION_FAILED",
  "message": "The submitted data is invalid.",
  "errors": {
    "name": [
      "The name field is required."
    ]
  }
}
```

العملية الحساسة تحتاج تحققًا أحدث — HTTP 403:

```json
{
  "success": false,
  "code": "REAUTHENTICATION_REQUIRED",
  "message": "Verify your phone again before continuing.",
  "errors": {}
}
```

### سلوك التطبيق حسب الخطأ

| HTTP | code | تصرف التطبيق |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | امسح الجلسة غير الصالحة وافصل Socket وابدأ الدخول |
| 401 | `INVALID_CREDENTIALS` | اعرض تعذر الدخول؛ قد يكون الحساب مغلقًا أو غير متاح لهذا النوع |
| 403 | `REAUTHENTICATION_REQUIRED` | OTP للهاتف الحالي ثم إعادة العملية الأصلية |
| 403 | `ACCOUNT_DISABLED`, `ACCOUNT_PENDING`, `FORBIDDEN` | اعرض حالة الحساب/الصلاحية؛ لا تدخل حلقة تسجيل دخول |
| 404 | `NOT_FOUND` | حدّث القائمة؛ المورد غير موجود أو غير مملوك للمستخدم |
| 409 | `PHONE_ALREADY_EXISTS` | اطلب رقمًا آخر أو الدعم |
| 409 | `RESOURCE_CONFLICT` | تحقق من ارتباط FCM بالحساب السابق |
| 409 | `ACCOUNT_CLOSURE_BLOCKED` | تسوية الرصيد والمدفوعات/الاستردادات أولًا |
| 409 | `IDEMPOTENCY_CONFLICT` | أصلح إعادة استخدام المفتاح؛ لا تغيّر بيانات محاولة قائمة |
| 409 | `RECONCILIATION_REQUIRED` | أوقف إعادة الإنشاء وتابع حالة العملية عبر الدعم/الخادم |
| 422 | `VALIDATION_FAILED` | اعرض errors بجوار الحقول |
| 422 | `INVALID_OTP` | اسمح بإعادة إدخال الكود ضمن حدود المحاولات |
| 422 | `OTP_EXPIRED`, `OTP_CONSUMED` | ابدأ challenge جديدًا عند السماح بالإرسال |
| 422 | `INVALID_PHONE_NUMBER`, `PHONE_COUNTRY_MISMATCH`, `UNSUPPORTED_PHONE_COUNTRY`, `PHONE_TYPE_NOT_ALLOWED` | صحح الدولة/رقم الموبايل |
| 422 | `PHONE_NOT_VERIFIED` | الحساب يحتاج هاتفًا موثقًا قبل العملية؛ اعرض مسار الدعم إذا تعذر التصحيح |
| 429 | `OTP_COOLDOWN` | انتظر مؤقت إعادة الإرسال |
| 429 | `OTP_ATTEMPTS_EXCEEDED`, `RATE_LIMITED` | أوقف التكرار وانتظر السماح بالمحاولة |
| 503 | `PROVIDER_UNAVAILABLE` | اعرض تعذر الخدمة مؤقتًا مع retry محدود |
| 500 | `INTERNAL_ERROR` | احتفظ بـX-Request-ID للدعم بدون تسريب التوكن |

### القيم الافتراضية

| الحد | القيمة |
| --- | --- |
| صلاحية OTP | 300 ثانية |
| إعادة الإرسال | بعد 60 ثانية |
| محاولات التحقق للكود | 5 |
| الإرسال لكل هاتف عبر الأغراض والأنواع | 6 في الساعة |
| HTTP طلب OTP | 3 في الدقيقة لكل IP |
| HTTP تحقق OTP | 5 في الدقيقة لكل IP |
| API المحمي العام | 60 في الدقيقة لكل مستخدم |
| إصدار Socket/تسجيل جهاز/رفع صورة/إنشاء دفعة/إغلاق حساب | 5 في الدقيقة لكل IP عبر limiter مشترك |
| صلاحية REST token | 1440 دقيقة |
| نافذة التحقق الحديث | 5 دقائق |
| الجلسات | 10 |
| صلاحية Socket token | 120 ثانية |

حدود الطلبات قد تتراكم: حد الـIP وحد الهاتف وحد المجموعة المحمية يمكن أن يطبقوا معًا. هذه قيم الإعدادات الافتراضية وليست ضمانًا ثابتًا لكل نشر. احترم Retry-After إذا ظهر في 429؛ لا تفترض وجوده في كل خطأ OTP. لا تستخدم الطلبات المرفوضة أثناء cooldown كوسيلة polling؛ قد تؤثر على حصة المحاولات.

عند فقدان استجابة تحقق OTP قد يكون الكود استُهلك بالفعل. لا تعِد المحاولة بلا حدود، ولا تفترض أن نفس challenge سيعيد توكن الدخول السابق. إذا لم تحصل على التوكن، ابدأ دخولًا جديدًا بعد السماح بالإرسال.


## التواريخ

تواريخ الموارد عادة ISO-8601، بينما expires_at في Socket token هو Unix seconds. احفظ المبلغ integer بعملة المحفظة؛ خصائص المزود التجاري لها عقد مستقل. لا تعتمد على ترتيب حقول JSON أو message المترجمة؛ استخدم code والحقول الموثقة.

[منع التكرار](outbox-and-idempotency.md) · [الإشعارات](../Client/notifications.md)

## فلترة القوائم وترتيبها

راجع [العقد الموحد للفلاتر والبحث والترتيب](list-filtering.md) للحقول المسموحة لكل مورد، وصيغ sort/sort_type ونطاق التاريخ والمبالغ. القوائم المرقمة تقبل per_page من 1 إلى 100؛ القوائم الكاملة لا تتغير استجابتها. معاملات غير مدعومة ترجع 422، ومزامنة الإشعارات والشات لها معاملات وترتيب ثابتان.
