<div align="center">

<img src="https://capsule-render.vercel.app/api?type=waving&color=0:0f2027,50:203a43,100:2c5364&height=200&section=header&text=For_You&fontSize=52&fontColor=ffffff&fontAlignY=38&desc=E-Commerce%20%C2%B7%20Influencer%20Affiliate%20System&descSize=20&descAlignY=58&animation=fadeIn" width="100%" alt="header" />

<a href="https://git.io/typing-svg">
<img src="https://readme-typing-svg.demolab.com?font=JetBrains+Mono&weight=600&size=20&duration=3000&pause=900&color=38BDF8&center=true&vCenter=true&width=720&lines=Laravel+REST+API+%C2%B7+Influencer+Affiliate+System;Promotional+links+%C2%B7+Tracked+clicks+%C2%B7+Commissions;Wallet+%C2%B7+Withdrawals+%C2%B7+Four-eyes+approval;Safe+order+state+machine+%C2%B7+Idempotent+checkout" alt="Typing SVG" />
</a>

<br/>

[![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-0ea5e9?style=for-the-badge)](LICENSE)

</div>

---

## 📖 نظرة عامة

**For_You** هو REST API لمتجر إلكتروني مبني بـ Laravel، مع نظام **تسويق بالعمولة
(Influencer Affiliate)**. المؤثر يمتلك روابط ترويجية خاصة بمنتجات المتجر، والنظام
يتتبع النقرات والمبيعات، ويحسب العمولات، ويضيفها لمحفظته بعد انتهاء فترة الانتظار،
ثم يدير طلبات السحب بموافقة ودفع منفصلين (four-eyes).

- واجهة API فقط (JSON) — بدون واجهات عرض أو multi-tenancy.
- المصادقة بـ **Laravel Sanctum** (Bearer tokens، صلاحية افتراضية 7 أيام).
- الأدوار والصلاحيات على مستوى كل مسار (`admin`, `influencer`, `permission:*`).
- الرسائل مترجمة عربي/إنجليزي حسب هيدر `Accept-Language`.
- العملة **EGP**، والتوقيت الافتراضي `Africa/Cairo`.

---

## ✨ المميزات

| الميزة | الوصف |
| :--- | :--- |
| 🔗 روابط ترويجية | كود لكل مؤثر/منتج مع تتبع clicks و conversions |
| 🛒 دورة طلب آمنة | State machine بانتقالات مسموحة فقط + قفل صفوف |
| 🧾 سلة و Checkout | سلة للعميل + عرض سعر (quote) قبل إنشاء الطلب |
| 👥 أدوار وصلاحيات | Roles & Permissions على مستوى كل Route |
| 🎟️ كوبونات وبانرات | خصومات وإعلانات ديناميكية مع تحقق من صحة التواريخ |
| 🔔 إشعارات | تنبيهات للعملاء والمؤثرين والإدارة بعد commit المعاملة |
| 📊 تقارير | مبيعات، عمولات، أفضل المنتجات |
| 💳 مدفوعات | تأكيد يدوي + webhook لبوابة دفع (EasyKash skeleton) |
| 🚚 شحن | شحن يدوي + webhook لتحديث حالة الشحنة |
| 🔒 Idempotency | `Idempotency-Key` لمنع تكرار الطلبات عند إعادة المحاولة |
| 💰 محفظة وسحب | محفظة مؤثر + طلبات سحب بمبدأ الشخصين (four-eyes) |
| 🖼️ رفع الصور | تحويل تلقائي إلى WebP عبر Intervention Image |
| 🧾 Audit Log | تسجيل العمليات الحساسة (مدفوعات، عمولات، إلغاء تلقائي، …) |
| 🚦 Rate Limiting | Limits منفصلة لتسجيل الدخول والتسجيل والـ webhooks وغيرها |
| ⚡ كاش | كاش للصفحة الرئيسية مع تفريغ تلقائي عند تعديل المحتوى |

---

## 🧰 التقنيات

- PHP 8.4.1+ / Laravel 13
- MySQL (SQLite in-memory للاختبارات)
- Laravel Sanctum, Socialite
- Intervention Image 4 (تحويل الصور إلى WebP)
- PHPUnit 12

---

## ✅ المتطلبات

| المتطلب | ملاحظات |
| :--- | :--- |
| PHP **8.4.1+** | مع امتدادات `pdo_mysql` و `mbstring` و `openssl` و `fileinfo` و `curl` |
| Composer 2.x | التثبيت من `composer.lock` |
| MySQL 8+ / MariaDB 10.6+ | في الإنتاج. الاختبارات تستخدم SQLite in-memory |
| `gd` أو `imagick` | **اختياري** — مطلوب فقط لرفع/تحويل الصور |
| Node.js 20+ | **اختياري** — فقط لبناء أصول Vite |

> الـ API يقلع بدون `gd`/`imagick`، لكن نقاط رفع الصور ترجع خطأ واضحًا وقت التشغيل.

---

## 📂 هيكل المشروع

```text
app/
├── Contracts/                 # واجهات بوابات الدفع والشحن
├── Console/Commands/          # commissions:release, orders:expire-unpaid
├── Http/
│   ├── Controllers/Api/       # العملاء، المصادقة، المؤثرون، الـ webhooks
│   ├── Controllers/Admin/     # الإدارة والموظفون (محميون بالصلاحيات)
│   ├── Middleware/            # admin, influencer, permission, active, locale
│   └── Resources/             # تحويل المخرجات (OrderResource, …)
├── Models/
├── Notifications/
├── Services/                  # منطق العمل: الطلبات، الدفع، المحفظة، التسعير
│   ├── PaymentGateways/       # manual + easykash
│   └── ShippingGateways/      # manual
config/
├── store.php                  # إعدادات المتجر والشحن والعمولات
├── payments.php               # بوابات الدفع
├── services.php               # بيانات Socialite وغيرها
└── sanctum.php                # صلاحية التوكن
database/                      # migrations, factories, seeders
docs/API.md                    # مرجع الـ API الكامل
postman/                       # مجموعة Postman جاهزة للاستيراد
routes/api.php                 # كل مسارات الـ API
tests/Feature/                 # اختبارات السلوك الحساس
```

---

## 🚀 التشغيل محليًا

### الطريقة السريعة

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

### الطريقة خطوة بخطوة

```bash
# 1. استنساخ المشروع
git clone https://github.com/AbdalrhmanAbdoAlhade/For_You.git
cd For_You

# 2. تثبيت الاعتماديات (من ملف القفل)
composer install

# 3. إعداد البيئة
cp .env.example .env
php artisan key:generate

# 4. اضبط بيانات قاعدة البيانات في .env ثم:
php artisan migrate --seed

# 5. رابط التخزين العام (لصور المنتجات)
php artisan storage:link

# 6. التشغيل
php artisan serve
```

بعد التشغيل يكون الـ API على:

```text
http://localhost:8000/api
```

### إنشاء الـ Super Admin

`php artisan migrate --seed` ينشئ الصلاحيات والأدوار (`super-admin`,
`products-manager`, `orders-manager`, `influencers-manager`,
`finance-manager`) ويُنشئ حساب Super Admin من المتغيرات:

```dotenv
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=change-me
ADMIN_NAME="Super Admin"
ADMIN_PHONE=01000000000
```

> في الإنتاج يجب ضبط `ADMIN_EMAIL` و `ADMIN_PASSWORD` قبل أول seed.
> الأمر لا ينشئ منتجات أو بيانات تجريبية — فقط الأدوار والصلاحيات والأدمن.

### الاختبارات

```bash
php artisan test
```

الاختبارات تستخدم SQLite in-memory كما هو مضبوط في `phpunit.xml`، فلا تحتاج
قاعدة بيانات محلية. لتشغيل ملف أو مجموعة محددة:

```bash
php artisan test tests/Feature/OrderTransitionTest.php
php artisan test --filter=CommissionTest
```

تغطي الاختبارات المدفوعات والـ webhooks والعمولات والسحب والصلاحيات
والـ idempotency وكاش الصفحة الرئيسية.

---

## 🧭 الأدوار والصلاحيات

| النوع | الوصف |
| :--- | :--- |
| `customer` | عميل المتجر — سلة، طلبات، عناوين، إشعارات |
| `influencer` | مؤثر — منتجاته، روابطه، مبيعاته، محفظته |
| `employee` | موظف إدارة — يصل حسب الصلاحيات الممنوحة لدوره |
| `admin` | Super Admin — يتجاوز فحص الصلاحيات |

- المسارات المحمية تستخدم `auth:sanctum` + `active`.
- `influencer` تتحقق من نوع المستخدم.
- `admin` تسمح بـ `admin` أو `employee`.
- `permission:a,b` تكفي أي صلاحية من القائمة.

أمثلة على الصلاحيات: `products.*`, `orders.*`, `customers.*`,
`influencers.*`, `withdrawals.*`, `payments.*`, `shipping.*`,
`reports.*`, `roles.manage`, `employees.*`, `banners.*`, `coupons.*`.

---

## 🔁 دورة الطلب

```text
new → confirmed → processing → ready_for_shipping → shipped → delivered
 │        │            │                │              │
 └────────┴────────────┴────────────────┘              └──→ returned
                    cancelled
```

| من | إلى |
| :--- | :--- |
| `new` | `confirmed`, `cancelled` |
| `confirmed` | `processing`, `cancelled` |
| `processing` | `ready_for_shipping`, `cancelled` |
| `ready_for_shipping` | `shipped`, `cancelled` |
| `shipped` | `delivered`, `returned` |
| `delivered` | `returned` |
| `cancelled` / `returned` | نهائية |

أي انتقال غير مسموح يرجع `422`.

### Idempotency

- `POST /api/orders` يتطلب هيدر `Idempotency-Key` (وإلا `422`).
- نفس المفتاح ونفس البيانات يعيد نفس الاستجابة `201` مع
  `Idempotent-Replayed: true`.
- نفس المفتاح ببيانات مختلفة يرجع `422`.
- طلب متزامن بنفس المفتاح يرجع `409`.
- الاستجابات الناجحة تُحفظ 7 أيام في الكاش.

---

## 🔐 دورة العمولة

```text
العميل يشتري عبر رابط مؤثر
        │
        ▼
عند تحويل الطلب إلى delivered تُنشأ العمولة بحالة pending
        │
        ▼
بعد انتهاء فترة الانتظار (INFLUENCER_COMMISSION_HOLD_DAYS) تصبح approved
        │
        ▼
تُضاف إلى محفظة المؤثر وتصبح قابلة للسحب
        │
        ▼
عند الإلغاء أو الإرجاع تُعكس العمولة (مع دعم الرصيد السالب كمديونية)
```

- نسبة العمولة لكل منتج عبر pivot `influencer_product.commission_percentage`،
  مع نسبة افتراضية من `INFLUENCER_DEFAULT_COMMISSION`.
- `NULL` في `influencer_product.commission_percentage` يعني «استخدم النسبة
  الافتراضية»، و `0` يعني «مؤهل بعمولة صفر».
- `NULL` في `order_items.commission_percentage` (المُجمّدة وقت الطلب) يعني أن
  السطر غير مؤهل للعمولة أصلًا.
- عكس آلي للعمولات عند الإلغاء/الإرجاع.
- المحفظة تدعم الرصيد السالب (مديونية على المؤثر).
- كل عمليات المحفظة والعمولات داخل `DB::transaction` مع `lockForUpdate`.

> «المبيعات» في لوحة الإدارة والتقارير ولوحة المؤثر تعني الطلبات بحالة
> `delivered` فقط (`Order::SALE_STATUSES`).

---

## 💸 السحب (Four-eyes)

```text
المؤثر يطلب السحب (pending)
        │
        ▼
الموافق (withdrawals.approve) يوافق أو يرفض
        │
        ▼
المُحوِّل (withdrawals.pay) يسجل التحويل ويحفظ transfer_reference
```

- الحالات: `pending → approved → paid` أو `pending → rejected`.
- عند `WITHDRAWAL_FOUR_EYES=true` لا يمكن لنفس الموظف أن يوافق ثم يدفع.
- الحد الأدنى للسحب: **50 EGP**.
- المبلغ لا يتجاوز الرصيد المتاح (الرصيد ناقص طلبات السحب المعلقة).
- طرق السحب: `vodafone_cash`, `instapay`, `bank_transfer`, `orange_cash`,
  `etisalat_cash`.
- كل تغييرات المحفظة داخل معاملة مع أقفال صفوف، مع Audit Log.

---

## 💳 المدفوعات

### الدفع اليدوي

- `POST /api/orders/{id}/pay` يجهّز عملية دفع بحالة `pending`.
- يؤكدها الإدمن عبر `PATCH /api/admin/payments/{id}/status` بحالة `paid`
  مع `transaction_id` (مطلوب عند التأكيد).
- الحالات المدعومة: `paid`, `failed`, `refunded`.

### EasyKash

- عند ضبط `EASYKASH_INITIATE_URL` تُرجع نقطة الدفع `payment_url`.
- رابط الدفع موقّع بـ HMAC-SHA256 على معاملات مرتبة
  (`order_number`, `amount`, `currency`, `reference`, `timestamp`,
  `merchant_id`) باستخدام `EASYKASH_INITIATE_SECRET` (أو
  `EASYKASH_WEBHOOK_SECRET` لو غير مضبوط).
- التحقق وأقفال الصفوف داخل معاملة قصيرة، ونداء البوابة نفسها خارج المعاملة.

### Webhook الدفع

`POST /api/webhooks/payments/{gateway}` — عام، محمي بالتوقيع.

```http
X-EasyKash-Signature: <hex hmac-sha256>
```

- التوقيع: `hash_hmac('sha256', rawBody, EASYKASH_WEBHOOK_SECRET)`.
- توقيع خاطئ → `401`، بوابة غير معروفة → `404`.
- إعادة إرسال حدث `paid` آمنة (idempotent) → `200` بحالة `already_paid`.
- فرق المبلغ أكبر من `payments.amount_tolerance` → `422` مع Audit Log.
- حدث `failed` لا يلغي الطلب؛ يبقى `new` حتى يعيد العميل المحاولة أو يلغيه
  `orders:expire-unpaid`.
- حدث `paid` متأخر بعد إلغاء/إرجاع الطلب يُسجل كـ `paid` مع Audit Log
  `payment.webhook.late_paid` ويرجع `200` بحالة `needs_refund` دون تغيير حالة
  الطلب — الاسترداد يدوي من المالية.
- عند `EASYKASH_WEBHOOK_TOLERANCE > 0` يجب أن يحتوي الحمولة على `timestamp`
  داخل النافذة المسموحة وإلا يُرفض `401`.

---

## 🚚 الشحن

### إنشاء شحنة يدويًا

`POST /api/admin/orders/{order}/shipping`:

```json
{
  "shipping_company": "Aramex",
  "tracking_number": "TRK-001",
  "shipping_address": "12 Nile Street, Cairo"
}
```

- الطلب يجب أن يكون `ready_for_shipping` (أو `shipped` مسبقًا).
- يجب أن يكون الدفع `paid` عند `REQUIRE_PAYMENT_BEFORE_SHIPPING=true`.
- شحنة واحدة لكل طلب.

### حالات الشحنة

```text
pending → picked_up → in_transit → out_for_delivery → delivered
   │           │            │               │
   └───────────┴────────────┴───────────────┴──→ failed / returned
failed → pending (retry) أو returned
returned → نهائية
```

تحديث الشحنة يزامن حالة الطلب:

- `picked_up` / `in_transit` / `out_for_delivery` → الطلب `shipped`
- `delivered` → الطلب `delivered`
- `returned` → الطلب `returned`

إذا كانت الشحنة `returned` والدفع ما زال `paid`: تُقبل الشحنة لكن حالة الطلب
لا تتغير، ويُسجَّل Audit Log `shipping.returned.needs_refund`. استرد الدفع أولًا
(`refunded`) ثم يمكن نقل الطلب إلى `returned`.

### Webhook الشحن

`POST /api/webhooks/shipping/{gateway}` — التوقيع في
`X-Shipping-Signature` باستخدام `SHIPPING_WEBHOOK_SECRET`، ويحتاج
`tracking_number` أو `order_number` على الأقل.

---

## 🗺️ ملخص الـ API

| المجموعة | البادئة | المصادقة |
| :--- | :--- | :--- |
| المصادقة | `/api/register`, `/api/login`, `/api/auth/*` | عام (بعضها محمي) |
| المتجر | `/api/home`, `/api/categories`, `/api/products`, `/api/influencers` | عام |
| تتبع الروابط | `GET /api/track/{code}` | عام |
| الحساب | `/api/auth/me`, `/profile`, `/change-password`, `/logout` | Sanctum |
| العناوين | `/api/addresses` | Sanctum |
| السلة | `/api/cart` | Sanctum |
| الطلبات | `/api/orders`, `/api/checkout` | Sanctum |
| الإشعارات | `/api/notifications` | Sanctum |
| المؤثر | `/api/influencer/*` | Sanctum + `influencer` |
| الإدارة | `/api/admin/*` | Sanctum + `admin` + permission |
| الـ Webhooks | `/api/webhooks/{payments,shipping}/{gateway}` | توقيع HMAC |

المرجع الكامل مع أجسام الطلبات والأمثلة في
[`docs/API.md`](docs/API.md)، ومجموعة Postman جاهزة في
[`postman/For_You_API.postman_collection.json`](postman/For_You_API.postman_collection.json).

---

## ⚡ التخزين المؤقت

- الصفحة الرئيسية `GET /api/home` تُخزَّن 5 دقائق تحت المفتاح
  `api.home.payload`، ويُفرَّغ الكاش تلقائيًا عند تعديل المنتجات أو الأقسام أو
  البانرات أو المؤثرين من لوحة الإدارة.
- الـ Idempotency يعتمد على الكاش أيضًا، لذا في الإنتاج استخدم مخزنًا مشتركًا
  (`database` أو `redis`) وليس `array` أو `file` على أكثر من سيرفر.

---

## ⚙️ متغيرات البيئة المهمة

راجع `.env.example` للقائمة الكاملة. أهم المتغيرات:

| المتغير | الافتراضي | الوصف |
| :--- | :--- | :--- |
| `APP_DEBUG` | `true` (مثال) | **يجب** أن يكون `false` في الإنتاج |
| `APP_TIMEZONE` | `Africa/Cairo` | المنطقة الزمنية المستخدمة في التقارير والتواريخ |
| `APP_LOCALE` | `ar` | لغة الرسائل الافتراضية |
| `DB_*` | MySQL | إعدادات قاعدة البيانات |
| `DB_TIMEZONE` | — | توقيت جلسة MySQL (اختياري، مثل `+00:00`) |
| `CACHE_STORE` | `database` | مخزن الكاش (مشترك مطلوب للـ idempotency) |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost,127.0.0.1` | نطاقات Sanctum للـ SPA |
| `SANCTUM_EXPIRATION` | `10080` | صلاحية التوكن بالدقائق (0 = بلا انتهاء) |
| `TRUSTED_PROXIES` | — | IPs/نطاقات البروكسي الموثوقة (أو `*`) خلف Cloudflare |
| `FRONTEND_URL` | `http://localhost:3000` | رابط الواجهة (reset password) |
| `ADMIN_URL` | `http://localhost:5173` | رابط لوحة الإدارة |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | — | إنشاء Super Admin عند `migrate --seed` (مطلوب في production) |
| `PAYMENT_GATEWAY` | `manual` | بوابة الدفع الافتراضية |
| `DEFAULT_PAYMENT_METHOD` | `manual` | طريقة الدفع الاحتياطية |
| `PAYMENT_METHODS` | `manual,easykash` | طرق الدفع المقبولة في الطلبات |
| `EASYKASH_WEBHOOK_SECRET` | — | سر HMAC لتوقيع webhook الدفع |
| `EASYKASH_SIGNATURE_HEADER` | `X-EasyKash-Signature` | اسم هيدر توقيع الدفع |
| `EASYKASH_INITIATE_SECRET` | يرجع للـ webhook secret | سر توقيع رابط الدفع |
| `EASYKASH_WEBHOOK_TOLERANCE` | `0` | نافذة قبول timestamp بالثواني (0 = معطّلة) |
| `EASYKASH_MERCHANT_ID` / `EASYKASH_INITIATE_URL` / `EASYKASH_MODE` | — | بيانات بوابة EasyKash |
| `SHIPPING_GATEWAY` | `manual` | بوابة الشحن |
| `SHIPPING_WEBHOOK_SECRET` | — | سر HMAC لتوقيع webhook الشحن |
| `SHIPPING_SIGNATURE_HEADER` | `X-Shipping-Signature` | اسم هيدر توقيع webhook الشحن |
| `SHIPPING_FLAT_RATE` | `50` | تكلفة الشحن الثابتة |
| `SHIPPING_FREE_OVER` | — | شحن مجاني فوق مبلغ معين |
| `INFLUENCER_DEFAULT_COMMISSION` | `10` | نسبة العمولة الافتراضية |
| `INFLUENCER_COMMISSION_HOLD_DAYS` | `7` | أيام انتظار العمولة بعد التسليم |
| `UNPAID_ORDER_TTL_HOURS` | `24` | إلغاء الطلبات غير المدفوعة تلقائيًا (0 = تعطيل) |
| `EXPIRE_UNPAID_PAYMENT_METHODS` | `easykash` | طرق الدفع المؤهلة للإلغاء التلقائي |
| `WITHDRAWAL_FOUR_EYES` | `true` | منع نفس الأدمن من الموافقة والدفع |
| `REQUIRE_PAYMENT_BEFORE_SHIPPING` | `true` | منع الشحن قبل تأكيد الدفع |
| `LINK_CLICK_TTL_MINUTES` | `60` | تجاهل تكرار نفس الزائر لنفس الرابط |
| `EMAIL_VERIFICATION_ENABLED` | `false` | تفعيل التحقق من الإيميل (skeleton) |
| `GOOGLE_CLIENT_IDS` | — | Google Client IDs المسموحة للـ id_token |
| `APPLE_*` | — | إعدادات Sign in with Apple |

---

## ⏱️ Cron / Scheduler

يجب تشغيل Laravel Scheduler في الإنتاج:

```cron
* * * * * cd /path/to/For_You && php artisan schedule:run >> /dev/null 2>&1
```

المهام المجدولة في `routes/console.php`:

| الأمر | التكرار | الوظيفة |
| :--- | :--- | :--- |
| `commissions:release` | كل ساعة | تحويل العمولات المنتهية من `pending` إلى `approved` وإضافتها للمحفظة |
| `orders:expire-unpaid` | كل 15 دقيقة | إلغاء الطلبات غير المدفوعة القديمة وإرجاع المخزون |

- `orders:expire-unpaid` يلغي فقط الطلبات التي طريقة دفعها ضمن
  `EXPIRE_UNPAID_PAYMENT_METHODS` (الدفع اليدوي/COD مستثنى)، ويكتب Audit Log
  `order.auto_cancelled`.
- كلا الأمرين يستخدم `withoutOverlapping()`.

---

## 🛡️ ملاحظات أمان

- `APP_DEBUG=false` في الإنتاج، ولا ترفع `.env` أو السجلات أو الكاش.
- اضبط `EASYKASH_WEBHOOK_SECRET` و `SHIPPING_WEBHOOK_SECRET` بقيم قوية.
- فعّل `EASYKASH_WEBHOOK_TOLERANCE` (مثل `300`) لمنع إعادة إرسال الـ webhook.
- اضبط `TRUSTED_PROXIES` عند العمل خلف Cloudflare/Load Balancer حتى تُقرأ IP
  العميل الحقيقية في الـ rate limiting.
- عدّل `SANCTUM_EXPIRATION` حسب سياسة الأمان لديك.
- كل تغييرات الحالة المالية داخل معاملات مع `lockForUpdate`، وكل العمليات
  الحساسة تُسجَّل في `audit_logs`.

---

## 📚 التوثيق

- [دليل الـ API الكامل](docs/API.md)
- [مجموعة Postman](postman/For_You_API.postman_collection.json)
- [إرشادات المساهمة/الوكيل](AGENTS.md)

---

## 👤 المطوّر

**Abdalrhman AbdoAlhade** — Back-End Developer (PHP/Laravel & Go)

📧 abdo.king22227@gmail.com

[![Portfolio](https://img.shields.io/badge/Portfolio-0ea5e9?style=for-the-badge&logo=vercel&logoColor=white)](https://abdalrhman-abdo-alhade.vercel.app/)
[![LinkedIn](https://img.shields.io/badge/LinkedIn-0A66C2?style=for-the-badge&logo=linkedin&logoColor=white)](https://www.linkedin.com/in/abdalrhman-abdoalhade-6673312ba)
[![GitHub](https://img.shields.io/badge/GitHub-181717?style=for-the-badge&logo=github&logoColor=white)](https://github.com/AbdalrhmanAbdoAlhade)

---

## 📄 الرخصة

هذا المشروع مرخص تحت رخصة **MIT** — راجع [LICENSE](LICENSE).
