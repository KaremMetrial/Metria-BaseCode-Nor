# Client — محادثات الدعم ومرفقاتها

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. العميل ينشئ محادثة بـrequest_id وclient_message_id ثابتين؛ حد المحادثات الجديدة/الجارية 5.
2. اعرض الرسائل تصاعديًا، واحفظ next_after_id بعد معالجة الصفحة. واصل ما دام has_more=true.
3. لإرفاق ملف: ارفعه أولًا عبر media ثم أرسل media_ids (حتى 5) مع الرسالة. body مطلوب إن لم توجد مرفقات.
4. أرسل through_message_id بعد عرض الرسائل؛ يجب أن ينتمي للمحادثة.
5. العميل يغلق محادثته؛ الأدمن يدير الحالة والتعيين باستخدام version الأحدث.
6. إشعار الدعم يطلب من التطبيق جلب الرسائل عبر REST، بما فيها بعد إعادة الاتصال.

الحالات new/in_progress/resolved/closed. الرد على resolved يعيد فتحها، closed يمنع الرسائل الجديدة. تكرار نفس معرف الرسالة والنص ومجموعة المرفقات يعيد نفس الرسالة؛ تغييرها يعيد 409. لا توجد typing indicators أو أحداث read-receipt مستقلة. Client لا يرى محادثة عميل آخر؛ كل موظف يحمل support.read يرى صندوق الدعم.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/client/support/conversations` |
| `POST` | `/api/v1/client/support/conversations` |
| `GET` | `/api/v1/client/support/conversations/{conversation}` |
| `GET` | `/api/v1/client/support/conversations/{conversation}/messages` |
| `POST` | `/api/v1/client/support/conversations/{conversation}/messages` |
| `PATCH` | `/api/v1/client/support/conversations/{conversation}/read` |
| `POST` | `/api/v1/client/support/conversations/{conversation}/close` |

## `GET /api/v1/client/support/conversations`

Requires a bearer token for the Client actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `status` | query | لا | `{"type": "string", "enum": ["new", "in_progress", "resolved", "closed"]}` |
| `page` | query | لا | `{"type": "integer", "default": 1, "minimum": 1}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/client/support/conversations" \
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
        "id": 42,
        "subject": "Payment support",
        "status": "new",
        "version": 2,
        "last_message_id": 101,
        "client_read_through": 0,
        "staff_read_through": 0,
        "unread_count": 0,
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

## `POST /api/v1/client/support/conversations`

Requires a bearer token for the Client actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Support mutation rate limit: 30 requests/minute per account.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `client_message_id` | string | نعم | الصيغة: "uuid"  |
| `body` | string/null | اختياري/شرطي | maxLength: 5000  |
| `media_ids` | array | اختياري/شرطي | maxItems: 5  |
| `request_id` | string | نعم | الصيغة: "uuid"  |
| `subject` | string | نعم | maxLength: 200  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/client/support/conversations" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $CLIENT_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"request_id":"33333333-3333-4333-8333-333333333333","subject":"Payment support","client_message_id":"11111111-1111-4111-8111-111111111111","body":"Please check this payment."}'
```

### مثال الاستجابة — HTTP 201

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 42,
    "subject": "Payment support",
    "status": "new",
    "version": 2,
    "last_message_id": 101,
    "client_read_through": 0,
    "staff_read_through": 0,
    "unread_count": 0,
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `GET /api/v1/client/support/conversations/{conversation}`

Requires a bearer token for the Client actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `conversation` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/client/support/conversations/42" \
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
    "id": 42,
    "subject": "Payment support",
    "status": "new",
    "version": 2,
    "last_message_id": 101,
    "client_read_through": 0,
    "staff_read_through": 0,
    "unread_count": 0,
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `GET /api/v1/client/support/conversations/{conversation}/messages`

Requires a bearer token for the Client actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `conversation` | path | نعم | `{"type": "integer"}` |
| `after_id` | query | لا | `{"type": "integer", "minimum": 0}` |
| `per_page` | query | لا | `{"type": "integer", "minimum": 1, "maximum": 100}` |
| `page` | query | لا | `{"type": "integer", "default": 1, "minimum": 1}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/client/support/conversations/42/messages" \
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
        "id": 101,
        "conversation_id": 42,
        "sender_type": "client",
        "is_mine": true,
        "client_message_id": "11111111-1111-4111-8111-111111111111",
        "body": "Please check this payment.",
        "attachments": [],
        "created_at": "2026-10-11T10:00:00+00:00"
      }
    ],
    "meta": {
      "has_more": false,
      "next_after_id": 101
    }
  }
}
```

## `POST /api/v1/client/support/conversations/{conversation}/messages`

Requires a bearer token for the Client actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Support mutation rate limit: 30 requests/minute per account.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `conversation` | path | نعم | `{"type": "integer"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `client_message_id` | string | نعم | الصيغة: "uuid"  |
| `body` | string/null | اختياري/شرطي | maxLength: 5000  |
| `media_ids` | array | اختياري/شرطي | maxItems: 5  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/client/support/conversations/42/messages" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $CLIENT_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"client_message_id":"11111111-1111-4111-8111-111111111111","body":"Please check this payment."}'
```

### مثال الاستجابة — HTTP 201

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 101,
    "conversation_id": 42,
    "sender_type": "client",
    "is_mine": true,
    "client_message_id": "11111111-1111-4111-8111-111111111111",
    "body": "Please check this payment.",
    "attachments": [],
    "created_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `PATCH /api/v1/client/support/conversations/{conversation}/read`

Requires a bearer token for the Client actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Support mutation rate limit: 30 requests/minute per account.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `conversation` | path | نعم | `{"type": "integer"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `through_message_id` | integer | نعم | min: 0  |

### مثال الطلب

```sh
curl --fail-with-body -X PATCH "$API_BASE/client/support/conversations/42/read" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $CLIENT_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"through_message_id":101}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 42,
    "subject": "Payment support",
    "status": "new",
    "version": 3,
    "last_message_id": 101,
    "client_read_through": 101,
    "staff_read_through": 0,
    "unread_count": 0,
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `POST /api/v1/client/support/conversations/{conversation}/close`

Requires a bearer token for the Client actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Support mutation rate limit: 30 requests/minute per account.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `conversation` | path | نعم | `{"type": "integer"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `version` | integer | نعم | min: 1  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/client/support/conversations/42/close" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $CLIENT_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"version":2}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 42,
    "subject": "Payment support",
    "status": "closed",
    "version": 3,
    "last_message_id": 101,
    "client_read_through": 0,
    "staff_read_through": 0,
    "unread_count": 0,
    "created_at": "2026-10-11T10:00:00+00:00",
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [SupportConversationController.php](../../app/Http/Controllers/Client/SupportConversationController.php)
