# Admin — Socket.IO والاتصال اللحظي

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. استخدم REST token لإصدار Socket token قصير العمر.
2. اتصل بـSocket.IO باستخدام auth.token وwebsocket transport.
3. استمع إلى notification.created وnotification.state_changed وجدول مزامنة REST.
4. أعد إصدار الاعتماد عند انتهاء صلاحيته أو إعادة الاتصال، مع backoff.

expires_at هنا Unix seconds؛ العمر الافتراضي 120 ثانية. الغرفة private-user.ID تُختار من السيرفر. سحب REST token لا يبطل اعتماد Socket الصادر فورًا؛ افصل الجهاز محليًا عند الخروج. [مثال الاتصال والمزامنة](../Shared/realtime-protocol.md).

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/admin/realtime/token` |

## `POST /api/v1/admin/realtime/token`

Returns a short-lived signed Socket.IO token and its Unix expiry time. Use it for the caller’s private notification room. It cannot authenticate REST API requests.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Additional rate limit: 5 requests/minute per IP.

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/realtime/token" \
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
    "token": "REPLACE_WITH_RETURNED_SOCKET_TOKEN",
    "expires_at": 1791712920
  }
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [SocketTokenController.php](../../app/Http/Controllers/Shared/SocketTokenController.php)
