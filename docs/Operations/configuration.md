# إعدادات الخدمات

[الفهرس](../README.md) · [المرجع الفعلي .env.example](../../.env.example)

احفظ أسرار البيئة على الخادم. القيم في .env.example أمثلة، ولا تنسخ أسرار الإنتاج للتطبيق الأمامي. بعد تغيير بيئة منشورة ذات config cache أعد بناء الكاش وأعد تشغيل العمال والخدمات المناسبة.

| المجموعة | المتغيرات الأساسية | الاستخدام |
|---|---|---|
| التطبيق | APP_ENV, APP_DEBUG, APP_KEY, APP_URL, TRUSTED_PROXIES, CORS_ORIGIN | الروابط والتشفير والـproxy والمتصفحات المسموحة |
| البيانات | DB_*, REDIS_*, CACHE_STORE, QUEUE_CONNECTION | MySQL وRedis والتشغيل الخلفي |
| OTP | SMS_PROVIDER, TWILIO_*, OTP_TTL_SECONDS, OTP_COOLDOWN_SECONDS, OTP_MAX_ATTEMPTS, OTP_HOURLY_LIMIT, OTP_MOBILE_ONLY | الإرسال والتحقق والحدود |
| الحساب | AUTH_TOKEN_MINUTES, CLIENT_TERMS_VERSION, CLIENT_TERMS_URL, CLIENT_SUPPORT_URL | عمر الجلسة والقيم الاحتياطية للشروط والاستعادة |
| Stripe | PAYMENT_PROVIDER, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET, STRIPE_LIVEMODE | إنشاء وتسوية الدفعات |
| MyFatoorah | MYFATOORAH_API_KEY, MYFATOORAH_WEBHOOK_SECRET, MYFATOORAH_COUNTRY, MYFATOORAH_CURRENCY, MYFATOORAH_LIVE, MYFATOORAH_REDIRECT_URL, MYFATOORAH_FEATURES | حساب التاجر وميزاته |
| Socket | SOCKET_TOKEN_SECRET, SOCKET_IO_*, REDIS_PREFIX, REDIS_BROADCAST_CONNECTION | اعتماد قصير العمر وتوصيل الأحداث |
| Push | NOTIFICATIONS_FCM_ENABLED, FCM_PROJECT_ID, GOOGLE_APPLICATION_CREDENTIALS | FCM HTTP v1 وهوية الخدمة |
| Email | NOTIFICATIONS_EMAIL_ENABLED, MAIL_* | قنوات Admin/Vendor ذوي البريد الموثق؛ Client لا يعتمد البريد |
| الميديا | MEDIA_DISK, MEDIA_CLAMAV_BINARY | قرص خاص وفاحص ملفات |
| الحدود | RATE_LIMIT_API, RATE_LIMIT_WEBHOOKS, RATE_LIMIT_LOGIN, RATE_LIMIT_OTP_REQUEST, RATE_LIMIT_OTP_VERIFY, RATE_LIMIT_CONTACT | سقوف طلبات HTTP |

الصفحات المدارة المنشورة هي مرجع متطلبات الشروط عند توافرها؛ لا تعتمد على CLIENT_TERMS_VERSION وحده. config/media.php يحتوي حدود الحجم والمدة والأبعاد والحصص الأخرى.

## القنوات الخارجية

## Email and push delivery

Set `NOTIFICATIONS_EMAIL_ENABLED=true` with a real `MAIL_MAILER` and its credentials. Only verified email addresses receive domain mail; log/array transports are rejected for delivery. `NOTIFICATIONS_FCM_ENABLED=true` requires `FCM_PROJECT_ID` and `GOOGLE_APPLICATION_CREDENTIALS`, pointing to a securely mounted Google service-account JSON file outside the repository/public directory. Google Auth obtains short-lived OAuth tokens; the FCM HTTP v1 API sends messages.

An active authenticated actor registers a token at `POST /api/v1/{actor}/devices` with `{ "token": "..." }` and removes it at `DELETE /api/v1/{actor}/devices/{id}`. Tokens are encrypted at rest, omitted from responses and limited to ten devices per user. Ownership cannot transfer through another user's registration. Clients should unregister their device before logout/account switching and deduplicate `notification_id` values.

The scheduler dispatches pending channel deliveries independently of realtime publication. Unregistered FCM devices are removed after an authoritative provider response. Provider failures remain pending with a delay; eight failed attempts mark a delivery failed. Inspect `notification_deliveries` and failed jobs, correct the cause, and deliberately reset a failed delivery for retry through authorized operations. Replays can duplicate an email if the worker dies after SMTP accepts it but before the database records success.



الإشعارات داخل الحساب مستقلة عن قبول جهاز Push للإذن. تفضيلات marketing_push=false افتراضيًا. الاحتفاظ بـAPP_KEY ضروري لفك البيانات المشفرة؛ تغييره عشوائيًا لا يمثل إجراء تدوير مكتملًا.

يجب أن يكون SOCKET_TOKEN_SECRET مضبوطًا ومتطابقًا بين Laravel وSocket.IO، بطول32بايت على الأقل. عند استعمال REDIS_PASSWORD=null في ملف Laravel، يفسره Socket.IO كعدم وجود كلمة مرور أيضًا؛ Redis المحمي يحتاج القيمة الفعلية نفسها في الطرفين. يجب تمرير REDIS_DB كذلك، وعدم خلط قاعدة cache بقاعدة اتصال البث.
