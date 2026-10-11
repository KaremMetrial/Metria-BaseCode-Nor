# Client — Socket.IO والاتصال اللحظي

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
| `POST` | `/api/v1/client/realtime/token` |

## أمثلة التدفق بالتفصيل

### 9.1 إصدار توكن Socket — POST /realtime/token

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/realtime/token" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": {
    "token": "EXAMPLE_SIGNED_SOCKET_TOKEN",
    "expires_at": 1791712920
  }
}
```

صلاحية افتراضية 120 ثانية، وexpires_at بالثواني منذ Unix epoch. هذا اعتماد مختلف عن REST Bearer token ولا يصلح لطلبات HTTP المحمية. يجب أن يكون Socket endpoint مهيأ في إعدادات تطبيقك.

مثال JavaScript باستخدام `socket.io-client` في تطبيقك. الدالتان `scheduleNotificationSync` و`handleApiError` تمثلان دوال عليك تنفيذها في التطبيق؛ الأولى تجدول مزامنة [دليل مزامنة الإشعارات](notifications.md)، والثانية تتعامل مع الأخطاء وفق [عقد الأخطاء](../Shared/http-contract.md):

```js
import { io } from 'socket.io-client';

async function connectNotifications({ clientBase, socketOrigin, bearerToken,
                                      scheduleNotificationSync, handleApiError }) {
  const response = await fetch(`${clientBase}/realtime/token`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${bearerToken}`,
      'X-Locale': 'en',
    },
  });
  const payload = await response.json();
  if (!response.ok || !payload.success) {
    handleApiError(response.status, payload);
    return null;
  }
  const socket = io(socketOrigin, {
    transports: ['websocket'],
    auth: { token: payload.data.token },
    reconnection: false,
  });
  socket.on('connect', () => scheduleNotificationSync());
  socket.on('notification.created', () => scheduleNotificationSync());
  socket.on('notification.state_changed', () => scheduleNotificationSync());
  return { socket, expiresAt: payload.data.expires_at };
}
```

في مدير الاتصال الخاص بالتطبيق: عند انقطاع الشبكة أو قرب انتهاء الاعتماد، افصل الاتصال القديم، واطلب توكن Socket جديدًا بتأخير متزايد وحدود محاولات مناسبة، ثم اتصل مجددًا. المثال يعطّل reconnect الآلي لمنع إعادة استخدام اعتماد منتهي. تعامل مع connect_error/disconnect؛ عند 401 من REST ارجع لتدفق الدخول. عند logout أوقف إعادة الاتصال وافصل socket وامسح بيانات الحساب.

السيرفر يضم المستخدم تلقائيًا إلى `private-user.42` في المثال، ولا يحتاج العميل إرسال join. لا يمكنه الاشتراك في غرفة مستخدم آخر.

Payload حدث `notification.created` هو سجل الإشعار نفسه **بدون** غلاف success/data:

```json
{
  "id": "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa",
  "category": "security",
  "action": {
    "screen": "sessions"
  },
  "archived_at": null,
  "revision": 1,
  "title": "Account notification",
  "body": "Welcome! Complete your profile to get started.",
  "read_at": null,
  "created_at": "2026-10-11T10:00:00+00:00"
}
```

Payload حدث `notification.state_changed`:

```json
{
  "version": 3,
  "unread_count": 0
}
```

الأحداث تصل عبر Outbox وQueue وقد تتأخر أو تتكرر. تجاهل إصدارات الحالة الأقدم وادمج السجلات بالـID/revision، واعتبر الحدث إشارة لطلب المزامنة. API هو مرجع العدد والحالة. النشر المجدول يعالج الإشعارات المعلقة كل دقيقة مع زمن إضافي محتمل للطابور.

سحب REST token لا يبطل فورًا اعتماد Socket سبق إصداره؛ يظل حتى انتهاء صلاحيته القصيرة ويفصل السيرفر الاتصال عندها. لذلك افصل اتصال الجهاز محليًا عند الخروج. لا يوجد مسار Client لإنشاء إشعار عشوائي أو إرسال أحداث موثوقة باسم السيرفر.

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [SocketTokenController.php](../../app/Http/Controllers/Shared/SocketTokenController.php)
