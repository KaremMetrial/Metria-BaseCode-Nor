# Admin — إدارة المستخدمين والأدوار

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. اقرأ قائمة المستخدمين عبر users.read؛ لا توجد فلاتر type/status في هذا endpoint حاليًا.
2. اعتماد Vendor pending يحتاج vendors.approve؛ رفضه يحتاج vendors.reject؛ التغييرات الأخرى users.block.
3. أرسل status من active/blocked/suspended/inactive. لا يمكن تغيير حالة نفسك أو أدمن آخر.
4. إضافة role تحتاج super-admin والهدف Admin؛ الإضافة لا تزيل الأدوار الحالية.

لا توجد API لإنشاء Admin أو تحرير قاموس الأدوار والصلاحيات. Client/Vendor نوع حساب وليس role. راجع [الصلاحيات](permissions.md).

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/users` |
| `PATCH` | `/api/v1/admin/users/{user}/status` |
| `POST` | `/api/v1/admin/users/{user}/roles` |

## `GET /api/v1/admin/users`

Returns users across actor types, newest first.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `users.read`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `page` | query | لا | `{"type": "integer", "default": 1, "minimum": 1}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/users" \
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
        "id": 1,
        "name": "Admin User",
        "phone": "+201012345678",
        "phone_country_id": 1,
        "phone_verified_at": "2026-10-11T10:00:00+00:00",
        "type": "admin",
        "status": "active",
        "locale": "en",
        "timezone": "Africa/Cairo",
        "has_avatar": false,
        "avatar_url": null,
        "email": "admin@example.test"
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

## `PATCH /api/v1/admin/users/{user}/status`

Requires vendors.approve for vendor approval, vendors.reject for rejecting a pending vendor, or users.block for other state changes. Administrators cannot change their own status or another administrator’s status.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `user` | path | نعم | `{"type": "integer"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `status` | string | نعم | القيم: ["active", "blocked", "suspended", "inactive"]  |

### مثال الطلب

```sh
curl --fail-with-body -X PATCH "$API_BASE/admin/users/57/status" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"status":"active"}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 57,
    "name": "Vendor User",
    "phone": "+201012345678",
    "phone_country_id": 1,
    "phone_verified_at": "2026-10-11T10:00:00+00:00",
    "type": "vendor",
    "status": "active",
    "locale": "en",
    "timezone": "Africa/Cairo",
    "has_avatar": false,
    "avatar_url": null,
    "email": null
  }
}
```

## `POST /api/v1/admin/users/{user}/roles`

Requires the super-admin role. The target must be an administrator. Adds the role without removing existing roles; assigning the same role again is idempotent.

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `user` | path | نعم | `{"type": "integer"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `role` | string | نعم | القيم: ["super-admin", "finance-admin", "support-agent"]  |

### مثال الطلب

يفترض المثال أن المستخدم 2 أدمن آخر موجود، بينما المستخدم 57 في مثال الحالة بائع.

```sh
curl --fail-with-body -X POST "$API_BASE/admin/users/2/roles" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"role":"support-agent"}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": null
}
```

## التعامل مع الفشل

401: أعد الدخول. 403: راجع نوع الحساب وحالته والصلاحيات. 404: لا تفترض ملكية المورد. 422: أصلح الحقول حسب errors. 429: احترم Retry-After إن وُجد. 500: احتفظ بـX-Request-ID. 503: راجع تهيئة الخدمات ولا تكرر عملية مالية بمفتاح جديد. تفاصيل 409 وسلوك إعادة المحاولة موضحة في تسلسل الميزة.

## مصدر العقد

- [UserManagementController.php](../../app/Http/Controllers/Admin/UserManagementController.php)
