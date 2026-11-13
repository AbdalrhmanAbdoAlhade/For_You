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

- واجهة API فقط (JSON) — بدون multi-tenancy.
- المصادقة بـ **Laravel Sanctum** (Bearer tokens).
- الأدوار والصلاحيات على مستوى كل مسار (`admin`, `influencer`, `permission:*`).

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
- عكس آلي للعمولات عند الإلغاء/الإرجاع.
- المحفظة تدعم الرصيد السالب (مديونية على المؤثر).
- كل عمليات المحفظة والعمولات داخل `DB::transaction` مع `lockForUpdate`.

---

## ⚡ المميزات

| الميزة | الوصف |
| :--- | :--- |
| 🔗 روابط ترويجية | كود لكل مؤثر/منتج مع تتبع clicks و conversions |
| 🛒 دورة طلب آمنة | State machine بانتقالات مسموحة فقط + قفل صفوف |
| 👥 أدوار وصلاحيات | Roles & Permissions على مستوى كل Route |
| 🎟️ كوبونات وبانرات | خصومات وإعلانات ديناميكية |
| 🔔 إشعارات | تنبيهات للعملاء والمؤثرين والإدارة |
| 📊 تقارير | مبيعات، عمولات، أفضل المنتجات |
| 💳 مدفوعات | تأكيد يدوي + webhook لبوابة دفع (EasyKash skeleton) |
| 🚚 شحن | شحن يدوي + webhook لتحديث حالة الشحنة |
| 🔒 Idempotency | `Idempotency-Key` لإنشاء الطلب ومنع التكرار |

---

## 🧰 التقنيات

- PHP 8.3+ / Laravel 13
- MySQL (SQLite in-memory للاختبارات)
- Laravel Sanctum, Socialite
- Intervention Image 4 (تحويل الصور إلى WebP)
- PHPUnit 12

---

## 🚀 التشغيل محليًا

```bash
# 1. استنساخ المشروع
git clone https://github.com/AbdalrhmanAbdoAlhade/For_You.git
cd For_You

# 2. تثبيت الاعتماديات
composer install

# 3. إعداد البيئة
cp .env.example .env
php artisan key:generate

# 4. قاعدة البيانات
php artisan migrate --seed

# 5. التشغيل
php artisan serve
```

> يتطلب رفع الصور امتداد `gd` أو `imagick` في PHP. باقي الـ API يعمل بدونهما.

### الاختبارات

```bash
php artisan test
```

الاختبارات تستخدم SQLite in-memory كما هو مضبوط في `phpunit.xml`.

---

## ⚙️ متغيرات البيئة المهمة

راجع `.env.example` للقائمة الكاملة. أهم المتغيرات:

| المتغير | الافتراضي | الوصف |
| :--- | :--- | :--- |
| `APP_TIMEZONE` | `Africa/Cairo` | المنطقة الزمنية المستخدمة في التقارير والتواريخ |
| `APP_LOCALE` | `ar` | لغة الرسائل الافتراضية |
| `DB_*` | MySQL | إعدادات قاعدة البيانات |
| `DB_TIMEZONE` | — | توقيت جلسة MySQL (اختياري، مثل `+00:00`) |
| `FRONTEND_URL` | `http://localhost:3000` | رابط الواجهة (reset password) |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | — | إنشاء Super Admin عند `migrate --seed` (مطلوب في production) |
| `PAYMENT_GATEWAY` | `manual` | بوابة الدفع الافتراضية |
| `PAYMENT_METHODS` | `manual,easykash` | طرق الدفع المقبولة في الطلبات |
| `EASYKASH_WEBHOOK_SECRET` | — | سر HMAC لتوقيع webhook الدفع |
| `SHIPPING_GATEWAY` | `manual` | بوابة الشحن |
| `SHIPPING_WEBHOOK_SECRET` | — | سر HMAC لتوقيع webhook الشحن |
| `SHIPPING_SIGNATURE_HEADER` | `X-Shipping-Signature` | اسم هيدر توقيع webhook الشحن |
| `SHIPPING_FLAT_RATE` | `50` | تكلفة الشحن الثابتة |
| `SHIPPING_FREE_OVER` | — | شحن مجاني فوق مبلغ معين |
| `INFLUENCER_DEFAULT_COMMISSION` | `10` | نسبة العمولة الافتراضية |
| `INFLUENCER_COMMISSION_HOLD_DAYS` | `7` | أيام انتظار العمولة بعد التسليم |
| `UNPAID_ORDER_TTL_HOURS` | `24` | إلغاء الطلبات غير المدفوعة تلقائيًا (0 = تعطيل) |
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
