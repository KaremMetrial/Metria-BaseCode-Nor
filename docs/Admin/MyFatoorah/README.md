# MyFatoorah — خدمات حساب التاجر

[فهرس الأدمن](../README.md) · [المدفوعات والمحفظة](../../Shared/payment-providers.md)

كل المسارات تحت `/api/v1/admin/integrations/myfatoorah` وتحتاج Admin نشطًا وصلاحية myfatoorah.manage. أبقِ أسرار التاجر على الخادم؛ لا تمنح تطبيق العميل توكن الأدمن لاستخدام embedded checkout.

هذه العمليات التجارية منفصلة عن شحن محفظة العميل: Amount يستخدم decimal major units، والنجاح المحلي succeeded لا يثبت أن الفاتورة دُفعت أوأن KYC اكتمل. لا تنشئ هذه العمليات حركات wallet ledger تلقائيًا.

## ترتيب التكامل

1. اضبط البلد والعملة وبيئة sandbox والمفاتيح، ثم اقرأ capabilities.
2. اختر مسارًا: فاتورة hosted، جلسة embedded، اشتراك، مورد، أوشحن. ميزات capabilities تعكس التهيئة المحلية؛ يجب اعتمادها أيضًا لدى المزود.
3. استخدم مفتاح Idempotency في mutations واحفظ operation_id. اقرأ نتيجة المزود داخل result. نفس المفتاح والحمولة يعيدان النتيجة المحفوظة عند النجاح المعروف.
4. عند 503 أونتيجة مجهولة اقرأ سجل operations. لا ترسل mutation جديدة لتجاوز 409 RECONCILIATION_REQUIRED.
5. افحص مصدر المزود وentities؛ webhook قد يضع needs_refresh بدل تحديث كل snapshot مباشرةً. resolution يتطلب دليلًا ومرجعًا ولا ينفذ حركة جديدة عند المزود.

## الميزات

- [checkout](checkout.md)
- [customers-tokens](customers-tokens.md)
- [invoices](invoices.md)
- [operations](operations.md)
- [refunds](refunds.md)
- [reports](reports.md)
- [sessions](sessions.md)
- [shipping](shipping.md)
- [subscriptions](subscriptions.md)
- [suppliers](suppliers.md)
- [transfers](transfers.md)

### Configure the merchant account

Set these values in the deployment environment, never in committed source:

```dotenv
PAYMENT_PROVIDER=myfatoorah
MYFATOORAH_API_KEY=your-private-merchant-key
MYFATOORAH_WEBHOOK_SECRET=your-private-webhook-key
MYFATOORAH_LIVE=false
MYFATOORAH_COUNTRY=KWT
MYFATOORAH_CURRENCY=KWD
MYFATOORAH_REDIRECT_URL=https://your-frontend.example/payment/result
MYFATOORAH_FEATURES=payments,embedded,tokenization,reporting
```

Country and currency must match the merchant account. The defaults describe a Kuwait sandbox account; they do **not** identify your actual merchant account. For an Egyptian live account, use `EGY`, `EGP`, and `MYFATOORAH_LIVE=true` with the Egyptian account's private key. Each enabled country has its own key. All sandbox regions use `apitest.myfatoorah.com`; the live host is selected from a fixed allowlist. [Account keys and regional hosts](https://docs.myfatoorah.com/docs/api-key).

| Merchant country | Country value | Base currency | Live API host |
| --- | --- | --- | --- |
| Kuwait | KWT | KWD | api.myfatoorah.com |
| Bahrain | BHR | BHD | api.myfatoorah.com |
| Jordan | JOR | JOD | api.myfatoorah.com |
| Oman | OMN | OMR | api.myfatoorah.com |
| Saudi Arabia | SAU | SAR | api-sa.myfatoorah.com |
| United Arab Emirates | ARE | AED | api-ae.myfatoorah.com |
| Qatar | QAT | QAR | api-qa.myfatoorah.com |
| Egypt | EGY | EGP | api-eg.myfatoorah.com |

Run the normal migrations and `php artisan db:seed --class=RbacSeeder --no-interaction`. Grant `myfatoorah.manage` only to administrators who operate the merchant account. Existing finance roles do not receive it automatically; the existing super-admin gate has access. All integration routes require an active admin bearer token and this permission.

In the merchant portal, configure **webhook v2**, enable its secure key, and point it at `/api/v1/webhooks/payments/myfatoorah` on the application's public HTTPS domain. Subscribe to the events you use. The redirect URL is a customer navigation destination; visiting it or receiving `paymentId` is never proof of payment. Do not put API credentials or the webhook secret in frontend code.

Optional feature switches are `recurring`, `multivendor`, `transfers`, `shipping`, `auth_capture`, `card_verification`, and `mit`. Add them to the comma-separated `MYFATOORAH_FEATURES` only after activation for your merchant account and API key. A local switch does not enable a feature at MyFatoorah. The capabilities endpoint reports local configuration, while `payment-methods/list` asks the provider which payment methods your account actually supports.


## مصدر الأمثلة

طلبات التشغيل مبنية على [الكتالوج](../../../resources/myfatoorah-examples.json)، وresult من fixtures [اختبارات العمليات](../../../tests/Feature/MyFatoorahOperationsTest.php). أمثلة مزود اصطناعية توضح الشكل؛ لا تمثل استجابة حية أوتفعيل حساب. أسماء حقول المزود حساسة لحالة الأحرف. راجع رابط العقد الرسمي الخاص بالعملية عند بناء شاشة المزود.
