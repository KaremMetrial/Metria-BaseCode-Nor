# Client — الجلسات والخروج

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. بعد الدخول، اقرأ الجلسات واعرض اسم الجهاز ووقت النشاط.
2. احذف جلسة بعينها لإيقاف وصول ذلك الجهاز واشتراك Push الخاص به.
3. logout يسحب التوكن الحالي؛ logout-all يسحب جميع الجلسات.
4. امسح بيانات الحساب محليًا وافصل Socket.IO وأوقف محاولات إعادة الاتصال.

## المسارات

| Method | Endpoint |
|---|---|
| `POST` | `/api/v1/client/auth/logout` |
| `GET` | `/api/v1/client/auth/sessions` |
| `DELETE` | `/api/v1/client/auth/sessions/{session}` |
| `POST` | `/api/v1/client/auth/logout-all` |

## أمثلة التدفق بالتفصيل

كل جلسة تمثل Bearer token. `device_id` الخاص بالتثبيت وID سجل Push وID الجلسة ثلاث قيم مختلفة.

### 5.1 الجلسات — GET /auth/sessions

```sh
curl --fail-with-body -X GET "$CLIENT_BASE/auth/sessions" \
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
    "items": [
      {
        "id": 42,
        "device_name": "Ahmed iPhone",
        "is_current": true,
        "created_at": "2026-10-11T10:00:00+00:00",
        "last_used_at": "2026-10-11T10:02:00+00:00",
        "expires_at": "2026-10-12T10:00:00+00:00"
      },
      {
        "id": 41,
        "device_name": "Ahmed Android",
        "is_current": false,
        "created_at": "2026-10-11T09:00:00+00:00",
        "last_used_at": null,
        "expires_at": "2026-10-12T09:00:00+00:00"
      }
    ]
  }
}
```

القائمة الأحدث أولًا وتستبعد الجلسات المنتهية؛ لا تحتوي أسرار التوكنات.

### 5.2 سحب جلسة — DELETE /auth/sessions/{session}

```sh
curl --fail-with-body -X DELETE "$CLIENT_BASE/auth/sessions/41" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

استخدم ID من قائمة الجلسات. تُحذف معها اشتراكات Push التابعة لها. إذا سحبت الجلسة الحالية امسح التوكن المحلي وافصل Socket. جلسة مستخدم آخر أو جلسة محذوفة تعيد 404.

### 5.3 خروج الجهاز الحالي — POST /auth/logout

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/auth/logout" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": "You have signed out.",
  "data": null
}
```

يسحب التوكن الحالي واشتراك Push المرتبط به. بعد النجاح احذف بيانات المستخدم المحلية وافصل Socket. إعادة الطلب بنفس التوكن المسحوب ترجع 401. إذا فشل الاتصال، الخروج المحلي وحده لا يثبت سحب التوكن من السيرفر.

### 5.4 الخروج من الجميع — POST /auth/logout-all

```sh
curl --fail-with-body -X POST "$CLIENT_BASE/auth/logout-all" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $TOKEN"
```

الاستجابة: **HTTP 200**.

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

كل جلسات المستخدم واشتراكات Push تُسحب، بما فيها الحالية. طبّق تنظيف الحالة المحلية نفسه. لا تحتاج OTP حديثًا للخروج أو لسحب الجلسات.

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [LogoutController.php](../../app/Http/Controllers/Shared/LogoutController.php)
- [SessionController.php](../../app/Http/Controllers/Shared/SessionController.php)
