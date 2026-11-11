✨ Overview (نظرة عامة)

For You هو Backend API مبني بـ Laravel يجمع بين متجر إلكتروني متكامل ونظام تسويق بالعمولة (Influencer Marketing).

يتيح النظام للمؤثرين التسويق للمنتجات ومتابعة أرباحهم عبر محافظ إلكترونية، مع توفير آليات حماية وإدارة دقيقة للعمولات، إضافةً إلى لوحة تحكم وإدارة كاملة للمحتوى والصلاحيات.

⚡ Key Features (المميزات الرئيسية)

🛒 للعملاء (Customers)

Authentication: تسجيل بدعم Email + Apple Sign-In.

Catalog: تصفح المنتجات والتصنيفات والعروض.

Cart & Coupons: سلة مشتريات ديناميكية وكوبونات خصم.

Orders: عناوين متعددة وتتبع الطلبات وتأكيدها.

📣 للمؤثرين (Influencers)

Dashboard: لوحة تحكم وتتبع النقرات والتحويلات.

Affiliate Links: روابط ترويجية قابلة للتتبع.

Holding Period: فترة حجز حماية للعمولة (افتراضي 7 أيام).

Wallet & Payouts: محفظة إلكترونية وطلبات سحب للأرباح.

🛡️ للإدارة (Admin)

Roles & Permissions: نظام صلاحيات وأدوار مرن.

Catalog Management: إدارة المنتجات والتصنيفات.

Influencers Control: ربط المؤثرين ونسب العمولات.

Finance & Payouts: موافقة على السحب وتقارير المبيعات.

🧰 Tech Stack (التقنيات المستخدمة)

🔐 Commission System Logic (نظام العمولات)

graph TD
    A[العميل يشتري عبر رابط المؤثر] --> B[تغيير حالة الطلب إلى Delivered]
    B --> C[إنشاء العمولة بحالة Pending]
    C --> D{انقضاء فترة الحجز - 7 أيام}
    D -->|تمت الفترة| E[تحويل الحالة إلى Approved وإيداع الأرباح]
    D -->|إلغاء / إرجاع| F[عكس العمولة - دعم الرصيد السالب]


🧱 Directory Structure (هيكل المشروع)

app/
├── Http/Controllers/
│   ├── Api/             # Endpoints العملاء والمؤثرين
│   └── Admin/           # Endpoints لوحة الإدارة
├── Models/              # User, Product, Order, Wallet, Commission...
├── Services/            # Business Logic (Order, Wallet, Commission...)
├── Middleware/          # Auth & Role checks
└── Notifications/       # System & Email Notifications

database/migrations/     # Database Tables Architecture
routes/api.php           # REST API Endpoints


🚀 Installation & Setup (التثبيت والتشغيل)

# 1. Clone the repository
git clone https://github.com/AbdalrhmanAbdoAlhade/For_You.git
cd For_You

# 2. Install dependencies
composer install

# 3. Environment configuration
cp .env.example .env
php artisan key:generate

# 4. Configure Database in .env and run migrations
php artisan migrate

# 5. Run local server
php artisan serve


Scheduled Tasks (المهام المجدولة)

# Run scheduler
php artisan schedule:work

# Release matured commissions
php artisan commissions:release

# Expire unpaid orders
php artisan orders:expire-unpaid


👤 Developer Profile (عن المطور)

Abdalrhman AbdoAlhade

Senior Back-End Systems Engineer

🏗️ 50+ live production environments.

🌍 Serving MENA: KSA, Egypt, Kuwait, Oman, Turkey, Syria.

🤖 AI Integration: Gemini AI & LangChain.

🛠️ Tech: PHP (Laravel), Go, Python, Multi-tenant SaaS, Payments.

📬 Connect with me

📄 License

هذا المشروع مرخص تحت رخصة MIT.
