<div align="center">

<img src="https://capsule-render.vercel.app/api?type=waving&color=0:0f2027,50:203a43,100:2c5364&height=200&section=header&text=For_You&fontSize=52&fontColor=ffffff&fontAlignY=38&desc=E-Commerce%20%C2%B7%20Influencer%20Affiliate%20System&descSize=20&descAlignY=58&animation=fadeIn" width="100%" alt="header" />

<a href="https://git.io/typing-svg">
<img src="https://readme-typing-svg.demolab.com?font=JetBrains+Mono&weight=600&size=20&duration=3000&pause=900&color=38BDF8&center=true&vCenter=true&width=720&lines=Laravel+API+%C2%B7+Multi-tenant+ready;Influencer+links+%C2%B7+Tracked+clicks+%C2%B7+Commissions;Wallet+%C2%B7+Withdrawals+%C2%B7+Negative+balance+support;Secure+state+machine+%C2%B7+Idempotent+payments" alt="Typing SVG" />
</a>

<br/>

[![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-0ea5e9?style=for-the-badge)](LICENSE)

</div>

---

## 📖 نظرة عامة

**For_You** متجر إلكتروني متكامل بنظام **تسويق بالعمولة (Influencer Affiliate)** مستوحى من منصات مثل Boutiqaat. يتيح للمؤثرين إنشاء روابط ترويجية خاصة لمنتجات المتجر، وتتبع النقرات والمبيعات، وكسب عمولات تُضاف تلقائيًا لمحافظهم مع دورة حياة كاملة للطلب.

---

## 🔐 نظام العمولات

```javascript
العميل يشتري عبر رابط مؤثر
        │
        ▼
عند تحويل الطلب إلى `delivered` تُنشأ العمولة
        │
        ▼
الحالة تكون `pending` لفترة انتظار (افتراضي 7 أيام)
        │
        ▼
بعد انتهاء الفترة تتحول إلى `approved` وتُضاف للمحفظة
        │
        ▼
عند الإلغاء أو الإرجاع تُعكس العمولة (مع دعم الرصيد السالب كمديونية)
```

### خصائص العمولات

- 🎯 **عمولة لكل منتج** عبر `commission_percentage` (pivot)
- ⏳ **فترة انتظار قابلة للإعداد** قبل الاعتماد النهائي
- 🔄 **عكس آلي** للعمولة عند الإلغاء / الإرجاع
- 💳 **محفظة بهاية سالبة** — الرصيد السالب يُعامل كمديونية على المؤثر
- 🔒 **Idempotency + Locking** — `lockForUpdate` ومعالجة تكرار العمليات
- 🧾 **سحوبات** مع معالجة واعتماد من الأدمن

---

## ⚡ المميزات الرئيسية

| الميزة | الوصف |
| :--- | :--- |
| 🔗 **روابط ترويجية** | كل مؤثر لديه روابط خاصة بتتبع clicks و conversions |
| 🛒 **دورة طلب كاملة** | State machine مع حالات انتقال آمنة وقفل (locks) |
| 👥 **أدوار وصلاحيات** | Roles & Permissions على مستوى كل Route |
| 🎟️ **كوبونات وبانرز** | نظام خصومات وإعلانات ديناميكية |
| 🔔 **إشعارات** | تنبيهات للمؤثرين والعملاء والأدمن |
| 📊 **تقارير** | إحصائيات مبيعات وعمولات |
| 🔒 **حسابات محصورة** | المؤثر يرى My Products / Sales / Percentage / Wallet فقط |

---

## 🧰 التقنيات

<div align="center">

**Back-end**

<img src="https://skillicons.dev/icons?i=php,laravel&theme=dark" alt="backend" />

**Database & Infrastructure**

<img src="https://skillicons.dev/icons?i=mysql,redis,docker,linux&theme=dark" alt="infra" />

</div>

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

---

## 👤 المطوّر

**Abdalrhman AbdoAlhade**

Back-End Developer | Expert in PHP (Laravel) & Go

أبني أنظمة Backend آمنة وقابلة للتوسع، وأدير قواعد بيانات معقدة، وأتكامل مع بوابات الدفع والخدمات الخارجية.

🌍 أخدم منطقة الشرق الأوسط وما بعدها (السعودية، مصر، الكويت، عُمان، تركيا، سوريا...)
🏗️ أدير أكثر من 50 بيئة إنتاج حية
🤖 أدمج الذكاء الاصطناعي (Gemini AI & LangChain) في أنظمة حقيقية

**تواصل معي:**

📧 abdo.king22227@gmail.com

<div align="center">

[![Portfolio](https://img.shields.io/badge/Portfolio-0ea5e9?style=for-the-badge&logo=vercel&logoColor=white)](https://abdalrhman-abdo-alhade.vercel.app/)
[![Email](https://img.shields.io/badge/Email-EA4335?style=for-the-badge&logo=gmail&logoColor=white)](mailto:abdo.king22227@gmail.com)
[![LinkedIn](https://img.shields.io/badge/LinkedIn-0A66C2?style=for-the-badge&logo=linkedin&logoColor=white)](https://www.linkedin.com/in/abdalrhman-abdoalhade-6673312ba)
[![GitHub](https://img.shields.io/badge/GitHub-181717?style=for-the-badge&logo=github&logoColor=white)](https://github.com/AbdalrhmanAbdoAlhade)

</div>

---

## 📄 الرخصة

هذا المشروع مرخص تحت رخصة **MIT**.

تم بناء المشروع بعناية ليكون أساسًا قويًا لمنصات التجارة الإلكترونية بنظام التسويق بالعمولة.

<img src="https://capsule-render.vercel.app/api?type=waving&color=0:2c5364,100:0f2027&height=120&section=footer" width="100%" alt="footer" />
