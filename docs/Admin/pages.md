# Admin — إدارة الصفحات والشروط

[فهرس التوثيق](../README.md) · [عقد HTTP والأخطاء](../Shared/http-contract.md) · [كل المسارات](../ENDPOINTS.md)

الأمثلة توضيحية واقعية مطابقة لعقد التطبيق، وليست بيانات مستخدمين حقيقيين أو نتائج اتصال حي بمزود دفع. استبدل IDs والتوكنات والـOTP بالقيم الراجعة من بيئتك. الأمثلة تستخدم `X-Locale: en` لتثبيت لغة الرد.

## تسلسل الاستخدام

1. أنشئ مسودة slug دائم مع translations.en؛ أضف ar قبل النشر.
2. ارفع الصور بغاية page واربط media_ids واستخدم url داخل Markdown.
3. احفظ PATCH باستخدام draft_version الحالي لتجنب الكتابة فوق تعديل آخر.
4. انشر باستخدام draft_version وrequires_acceptance. النشر يتطلب عنوانًا ومحتوى باللغتين.
5. تعديل المسودة لا يغير النسخة العامة حتى نشر إصدار جديد. revisions تحفظ التاريخ.
6. archive يخفي الصفحة وصورها العامة؛ terms المنشورة لا يمكن أرشفتها.

نص Markdown يرجع خامًا ومعه HTML منظّف. عند تغيير شروط يتطلب قبولًا جديدًا، يتغير terms_version؛ التعديل التحريري مع requires_acceptance=false يحتفظ به. يجب ربط الصور في media_ids، لا يكفي وضع رابطها في النص. الصلاحيات pages.read/manage/publish مستقلة.

الـslugs الافتراضية موجودة بعد seeding؛ افتحها وعدّلها بدل POST مكرر. مثال إنشاء صفحة جديدة يستخدم help-center، وبقية الأمثلة تعرض صفحة about-us موجودة. الأمثلة مستقلة وليست أوامر تنفّذ كلها بالترتيب دون قراءة النسخ الحالية.

## المسارات

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/pages` |
| `POST` | `/api/v1/admin/pages` |
| `GET` | `/api/v1/admin/pages/{page}` |
| `PATCH` | `/api/v1/admin/pages/{page}` |
| `POST` | `/api/v1/admin/pages/{page}/publish` |
| `POST` | `/api/v1/admin/pages/{page}/archive` |
| `GET` | `/api/v1/admin/pages/{page}/revisions` |

## `GET /api/v1/admin/pages`

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `pages.read`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `status` | query | لا | `{"type": "string", "enum": ["draft", "published", "archived"]}` |
| `page` | query | لا | `{"type": "integer", "default": 1, "minimum": 1}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/pages" \
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
        "id": 8,
        "slug": "about-us",
        "status": "draft",
        "draft_version": 1,
        "media_ids": [],
        "sort_order": 0,
        "show_in_footer": true,
        "show_in_settings": true,
        "translations": {
          "en": {
            "title": "About us",
            "content": "Welcome to Metrial."
          },
          "ar": {
            "title": "من نحن",
            "content": "مرحبًا بكم في متريال."
          }
        },
        "published_revision_id": null,
        "terms_version": null,
        "published_version": null,
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

## `POST /api/v1/admin/pages`

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `pages.manage`.

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `slug` | string | نعم | maxLength: 100; pattern: "^[a-z0-9]+(?:-[a-z0-9]+)*$"  |
| `media_ids` | array | اختياري/شرطي | maxItems: 20  |
| `sort_order` | integer | اختياري/شرطي | min: 0; max: 65535  |
| `show_in_footer` | boolean | اختياري/شرطي |   |
| `show_in_settings` | boolean | اختياري/شرطي |   |
| `translations` | object | نعم |   |
| `translations.en` | object | نعم |   |
| `translations.en.title` | string | نعم | maxLength: 255  |
| `translations.en.content` | string/null | نعم | maxLength: 100000  |
| `translations.ar` | object | اختياري/شرطي |   |
| `translations.ar.title` | string | عند إرسال الأب | maxLength: 255  |
| `translations.ar.content` | string/null | عند إرسال الأب | maxLength: 100000  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/pages" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"slug":"help-center","translations":{"en":{"title":"Help center","content":"Welcome to Metrial."},"ar":{"title":"مركز المساعدة","content":"مرحبًا بكم في متريال."}},"show_in_footer":true,"show_in_settings":true,"media_ids":[]}'
```

### مثال الاستجابة — HTTP 201

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 8,
    "slug": "help-center",
    "status": "draft",
    "draft_version": 1,
    "media_ids": [],
    "sort_order": 0,
    "show_in_footer": true,
    "show_in_settings": true,
    "translations": {
      "en": {
        "title": "Help center",
        "content": "Welcome to Metrial."
      },
      "ar": {
        "title": "مركز المساعدة",
        "content": "مرحبًا بكم في متريال."
      }
    },
    "published_revision_id": null,
    "terms_version": null,
    "published_version": null,
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `GET /api/v1/admin/pages/{page}`

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `pages.read`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `page` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/pages/8" \
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
    "id": 8,
    "slug": "about-us",
    "status": "draft",
    "draft_version": 1,
    "media_ids": [],
    "sort_order": 0,
    "show_in_footer": true,
    "show_in_settings": true,
    "translations": {
      "en": {
        "title": "About us",
        "content": "Welcome to Metrial."
      },
      "ar": {
        "title": "من نحن",
        "content": "مرحبًا بكم في متريال."
      }
    },
    "published_revision_id": null,
    "terms_version": null,
    "published_version": null,
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `PATCH /api/v1/admin/pages/{page}`

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `pages.manage`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `page` | path | نعم | `{"type": "integer"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `media_ids` | array | اختياري/شرطي | maxItems: 20  |
| `sort_order` | integer | اختياري/شرطي | min: 0; max: 65535  |
| `show_in_footer` | boolean | اختياري/شرطي |   |
| `show_in_settings` | boolean | اختياري/شرطي |   |
| `translations` | object | اختياري/شرطي |   |
| `translations.en` | object | اختياري/شرطي |   |
| `translations.en.title` | string | عند إرسال الأب | maxLength: 255  |
| `translations.en.content` | string/null | عند إرسال الأب | maxLength: 100000  |
| `translations.ar` | object | اختياري/شرطي |   |
| `translations.ar.title` | string | عند إرسال الأب | maxLength: 255  |
| `translations.ar.content` | string/null | عند إرسال الأب | maxLength: 100000  |
| `draft_version` | integer | نعم | min: 1  |

### مثال الطلب

```sh
curl --fail-with-body -X PATCH "$API_BASE/admin/pages/8" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"draft_version":1,"translations":{"en":{"title":"About us","content":"Welcome to Metrial."},"ar":{"title":"من نحن","content":"مرحبًا بكم في متريال."}},"media_ids":[]}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 8,
    "slug": "about-us",
    "status": "draft",
    "draft_version": 2,
    "media_ids": [],
    "sort_order": 0,
    "show_in_footer": true,
    "show_in_settings": true,
    "translations": {
      "en": {
        "title": "About us",
        "content": "Welcome to Metrial."
      },
      "ar": {
        "title": "من نحن",
        "content": "مرحبًا بكم في متريال."
      }
    },
    "published_revision_id": null,
    "terms_version": null,
    "published_version": null,
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `POST /api/v1/admin/pages/{page}/publish`

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `pages.publish`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `page` | path | نعم | `{"type": "integer"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `draft_version` | integer | نعم | min: 1  |
| `requires_acceptance` | boolean | نعم |   |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/pages/8/publish" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"draft_version":1,"requires_acceptance":false}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 8,
    "slug": "about-us",
    "status": "published",
    "draft_version": 2,
    "media_ids": [],
    "sort_order": 0,
    "show_in_footer": true,
    "show_in_settings": true,
    "translations": {
      "en": {
        "title": "About us",
        "content": "Welcome to Metrial."
      },
      "ar": {
        "title": "من نحن",
        "content": "مرحبًا بكم في متريال."
      }
    },
    "published_revision_id": 12,
    "terms_version": null,
    "published_version": 1,
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `POST /api/v1/admin/pages/{page}/archive`

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `pages.publish`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `page` | path | نعم | `{"type": "integer"}` |

### حقول الطلب

Content-Type: `application/json`. الحقول الشرطية موضحة في تسلسل الاستخدام؛ حقل nested مطلوب فقط عندما يُرسل الأب.

| الحقل | النوع | مطلوب | القيود/الوصف |
|---|---|---|---|
| `draft_version` | integer | نعم | min: 1  |

### مثال الطلب

```sh
curl --fail-with-body -X POST "$API_BASE/admin/pages/8/archive" \
  -H 'Accept: application/json' \
  -H 'X-Locale: en' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"draft_version":1}'
```

### مثال الاستجابة — HTTP 200

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 8,
    "slug": "about-us",
    "status": "archived",
    "draft_version": 2,
    "media_ids": [],
    "sort_order": 0,
    "show_in_footer": true,
    "show_in_settings": true,
    "translations": {
      "en": {
        "title": "About us",
        "content": "Welcome to Metrial."
      },
      "ar": {
        "title": "من نحن",
        "content": "مرحبًا بكم في متريال."
      }
    },
    "published_revision_id": 12,
    "terms_version": null,
    "published_version": 1,
    "updated_at": "2026-10-11T10:00:00+00:00"
  }
}
```

## `GET /api/v1/admin/pages/{page}/revisions`

Requires a bearer token for the Admin actor.

The account must be active; pending or disabled accounts receive 403.

Rate limit: 60 requests/minute per authenticated user, or per IP for public routes.

Required permission: `pages.read`.

### Path / Query

| الحقل | المكان | مطلوب | القيود |
|---|---|---|---|
| `page` | path | نعم | `{"type": "integer"}` |

### مثال الطلب

```sh
curl --fail-with-body -X GET "$API_BASE/admin/pages/8/revisions" \
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
        "id": 12,
        "version": 1,
        "translations": {
          "en": {
            "title": "About us",
            "content": "Welcome to Metrial."
          },
          "ar": {
            "title": "من نحن",
            "content": "مرحبًا بكم في متريال."
          }
        },
        "navigation": {
          "sort_order": 0,
          "show_in_footer": true,
          "show_in_settings": true
        },
        "media_ids": [],
        "terms_version": null,
        "published_by": 1,
        "published_at": "2026-10-11T10:00:00+00:00"
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

- [PageController.php](../../app/Http/Controllers/Admin/PageController.php)
