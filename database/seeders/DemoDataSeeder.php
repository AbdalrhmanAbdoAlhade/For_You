<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Demo fixtures only. Never populate production with fake accounts/data.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoDataSeeder skipped: it is allowed only in local/testing environments.');
            return;
        }

        DB::transaction(function (): void {
            $this->seedEmployees();
            $categories = $this->seedCategoriesAndProducts();
            $this->seedInfluencers($categories['products']);
        });

        $this->command?->info('Demo data seeded successfully.');
        $this->command?->warn('Demo password for all seeded employees/influencers: password');
    }

    private function seedEmployees(): void
    {
        $employees = [
            ['name' => 'أحمد محمد', 'email' => 'staff.products@example.test', 'phone' => '01090000001', 'role' => 'products-manager'],
            ['name' => 'سارة علي', 'email' => 'staff.orders@example.test', 'phone' => '01090000002', 'role' => 'orders-manager'],
            ['name' => 'محمود حسن', 'email' => 'staff.influencers@example.test', 'phone' => '01090000003', 'role' => 'influencers-manager'],
            ['name' => 'منة إبراهيم', 'email' => 'staff.finance@example.test', 'phone' => '01090000004', 'role' => 'finance-manager'],
            ['name' => 'يوسف خالد', 'email' => 'staff.products2@example.test', 'phone' => '01090000005', 'role' => 'products-manager'],
            ['name' => 'نور أحمد', 'email' => 'staff.orders2@example.test', 'phone' => '01090000006', 'role' => 'orders-manager'],
            ['name' => 'عمر مصطفى', 'email' => 'staff.influencers2@example.test', 'phone' => '01090000007', 'role' => 'influencers-manager'],
            ['name' => 'ملك محمود', 'email' => 'staff.finance2@example.test', 'phone' => '01090000008', 'role' => 'finance-manager'],
        ];

        foreach ($employees as $data) {
            $role = Role::query()
                ->where('slug', $data['role'])
                ->where('is_active', true)
                ->firstOrFail();

            $employee = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => Hash::make('password'),
                    'type' => 'employee',
                    'is_active' => true,
                    'avatar' => null,
                ]
            );

            // Assign only the existing role; permissions come from RolesAndPermissionsSeeder.
            $employee->roles()->sync([$role->id]);
        }
    }

    /**
     * @return array{products: \Illuminate\Support\Collection<int, Product>}
     */
    private function seedCategoriesAndProducts(): array
    {
        $categoryDefinitions = [
            ['name' => 'العناية بالبشرة', 'name_en' => 'Skin Care', 'slug' => 'demo-skin-care', 'description' => 'منتجات يومية للعناية بالبشرة.', 'description_en' => 'Everyday skin care essentials.'],
            ['name' => 'العناية بالشعر', 'name_en' => 'Hair Care', 'slug' => 'demo-hair-care', 'description' => 'منتجات للعناية بالشعر وتصفيفه.', 'description_en' => 'Hair care and styling products.'],
            ['name' => 'العطور', 'name_en' => 'Perfumes', 'slug' => 'demo-perfumes', 'description' => 'عطور للاستخدام اليومي والمناسبات.', 'description_en' => 'Fragrances for everyday use and special occasions.'],
            ['name' => 'الإكسسوارات', 'name_en' => 'Accessories', 'slug' => 'demo-accessories', 'description' => 'إكسسوارات عملية وأنيقة.', 'description_en' => 'Practical and stylish accessories.'],
            ['name' => 'الصحة واللياقة', 'name_en' => 'Health & Fitness', 'slug' => 'demo-health-fitness', 'description' => 'مستلزمات الصحة واللياقة البدنية.', 'description_en' => 'Health and fitness essentials.'],
            ['name' => 'الأزياء', 'name_en' => 'Fashion', 'slug' => 'demo-fashion', 'description' => 'قطع أزياء مختارة للاستخدام اليومي.', 'description_en' => 'Selected everyday fashion pieces.'],
            ['name' => 'المنزل والمعيشة', 'name_en' => 'Home & Living', 'slug' => 'demo-home-living', 'description' => 'منتجات عملية للمنزل.', 'description_en' => 'Useful products for your home.'],
            ['name' => 'الإلكترونيات', 'name_en' => 'Electronics', 'slug' => 'demo-electronics', 'description' => 'إلكترونيات وملحقات تقنية.', 'description_en' => 'Electronics and tech accessories.'],
        ];

        $categories = [];
        foreach ($categoryDefinitions as $index => $data) {
            $categories[$data['slug']] = Category::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'name_en' => $data['name_en'],
                    'description' => $data['description'],
                    'description_en' => $data['description_en'],
                    'image' => 'https://placehold.co/800x600?text=' . rawurlencode($data['name_en']),
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'parent_id' => null,
                ]
            );
        }

        $productDefinitions = [
            ['skin', 'سيروم فيتامين سي', 'Vitamin C Serum', 350, 299, 45, true, 'سيروم يومي بتركيبة خفيفة.', 'Lightweight daily serum.'],
            ['skin', 'مرطب الوجه', 'Daily Face Moisturizer', 280, null, 60, true, 'ترطيب يومي للبشرة.', 'Daily facial hydration.'],
            ['skin', 'غسول لطيف', 'Gentle Facial Cleanser', 190, 165, 80, false, 'غسول مناسب للاستخدام اليومي.', 'A gentle everyday cleanser.'],
            ['hair', 'شامبو مغذي', 'Nourishing Shampoo', 220, null, 55, false, 'تنظيف وعناية بالشعر.', 'Cleansing and care for your hair.'],
            ['hair', 'بلسم مرطب', 'Hydrating Conditioner', 210, 185, 42, false, 'يساعد على تنعيم الشعر.', 'Helps leave hair feeling soft.'],
            ['hair', 'زيت شعر', 'Hair Care Oil', 260, null, 35, true, 'زيت للعناية بالشعر.', 'Hair care oil.'],
            ['perfumes', 'عطر روز', 'Rose Eau de Parfum', 650, 575, 25, true, 'عطر بنفحات زهرية.', 'A fragrance with floral notes.'],
            ['perfumes', 'عطر سيجنتشر', 'Signature Eau de Parfum', 790, null, 18, true, 'عطر مناسب للمناسبات.', 'A fragrance for special occasions.'],
            ['perfumes', 'بودي ميست', 'Body Mist', 240, 210, 70, false, 'رذاذ معطر للاستخدام اليومي.', 'A refreshing everyday body mist.'],
            ['accessories', 'ساعة كلاسيكية', 'Classic Watch', 1250, 1099, 12, true, 'ساعة بتصميم كلاسيكي.', 'A watch with a classic design.'],
            ['accessories', 'حقيبة يومية', 'Everyday Bag', 890, null, 20, false, 'حقيبة عملية للاستخدام اليومي.', 'A practical everyday bag.'],
            ['accessories', 'محفظة جلدية', 'Leather Wallet', 420, 375, 32, false, 'محفظة بتصميم بسيط.', 'A wallet with a clean design.'],
            ['health', 'زجاجة مياه رياضية', 'Sports Water Bottle', 180, null, 90, false, 'زجاجة قابلة لإعادة الاستخدام.', 'Reusable water bottle.'],
            ['health', 'حبل مقاومة', 'Resistance Band', 150, 125, 65, false, 'أداة بسيطة للتمارين المنزلية.', 'A simple home workout tool.'],
            ['health', 'حصيرة يوغا', 'Yoga Mat', 490, null, 24, true, 'حصيرة للتمارين واليوغا.', 'A mat for yoga and workouts.'],
            ['fashion', 'تيشيرت قطني', 'Cotton T-Shirt', 320, 275, 40, false, 'تيشيرت قطني مريح.', 'A comfortable cotton T-shirt.'],
            ['fashion', 'هودي أساسي', 'Essential Hoodie', 720, null, 16, true, 'هودي مناسب للاستخدام اليومي.', 'An everyday essential hoodie.'],
            ['fashion', 'كاب كلاسيكي', 'Classic Cap', 230, 199, 38, false, 'كاب بتصميم بسيط.', 'A cap with a simple design.'],
            ['home', 'كوب حراري', 'Thermal Mug', 290, null, 50, false, 'كوب مناسب للمشروبات الساخنة والباردة.', 'For hot and cold drinks.'],
            ['home', 'مصباح مكتب', 'Desk Lamp', 540, 475, 21, true, 'إضاءة عملية لمساحة العمل.', 'Practical lighting for your workspace.'],
            ['home', 'منظم مكتب', 'Desk Organizer', 160, null, 44, false, 'لترتيب أدوات المكتب.', 'Keeps desk essentials organized.'],
            ['electronics', 'سماعة لاسلكية', 'Wireless Earbuds', 1450, 1299, 15, true, 'سماعة لاسلكية للاستخدام اليومي.', 'Wireless earbuds for everyday use.'],
            ['electronics', 'شاحن سريع', 'Fast Charger', 390, null, 33, false, 'شاحن سريع متوافق مع أجهزة متعددة.', 'A fast charger for compatible devices.'],
            ['electronics', 'حامل هاتف', 'Phone Stand', 120, 99, 75, false, 'حامل مكتبي للهاتف.', 'A desktop phone stand.'],
        ];

        $products = collect();
        foreach ($productDefinitions as $index => $row) {
            [$categoryKey, $name, $nameEn, $price, $discount, $stock, $featured, $description, $descriptionEn] = $row;
            $slug = 'demo-' . Str::slug($nameEn);

            $product = Product::updateOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $categories['demo-' . $categoryKey . ($categoryKey === 'skin' ? '-care' : ($categoryKey === 'hair' ? '-care' : ($categoryKey === 'health' ? '-fitness' : ($categoryKey === 'home' ? '-living' : ($categoryKey === 'electronics' ? '' : '')))))]->id,
                    'name' => $name,
                    'name_en' => $nameEn,
                    'description' => $description,
                    'description_en' => $descriptionEn,
                    'short_description' => $description,
                    'short_description_en' => $descriptionEn,
                    'price' => $price,
                    'discount_price' => $discount,
                    'stock' => $stock,
                    'sku' => 'DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'images' => [
                        'https://placehold.co/800x800?text=' . rawurlencode($nameEn),
                    ],
                    'is_featured' => $featured,
                    'is_active' => true,
                ]
            );

            $products->push($product);
        }

        return ['products' => $products];
    }

    /**
     * @param \Illuminate\Support\Collection<int, Product> $products
     */
    private function seedInfluencers($products): void
    {
        $influencers = [
            ['name' => 'ليلى أحمد', 'email' => 'influencer01@example.test', 'phone' => '01190000001'],
            ['name' => 'كريم سامي', 'email' => 'influencer02@example.test', 'phone' => '01190000002'],
            ['name' => 'نورهان علي', 'email' => 'influencer03@example.test', 'phone' => '01190000003'],
            ['name' => 'عمر شريف', 'email' => 'influencer04@example.test', 'phone' => '01190000004'],
            ['name' => 'منة الله حسن', 'email' => 'influencer05@example.test', 'phone' => '01190000005'],
            ['name' => 'يوسف محمود', 'email' => 'influencer06@example.test', 'phone' => '01190000006'],
            ['name' => 'جنى محمد', 'email' => 'influencer07@example.test', 'phone' => '01190000007'],
            ['name' => 'آدم خالد', 'email' => 'influencer08@example.test', 'phone' => '01190000008'],
            ['name' => 'ملك إبراهيم', 'email' => 'influencer09@example.test', 'phone' => '01190000009'],
            ['name' => 'زياد مصطفى', 'email' => 'influencer10@example.test', 'phone' => '01190000010'],
        ];

        $productIds = $products->pluck('id')->values();

        foreach ($influencers as $index => $data) {
            $influencer = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => Hash::make('password'),
                    'type' => 'influencer',
                    'is_active' => true,
                    'avatar' => null,
                ]
            );

            // Empty wallets are intentional: no fake money or financial history is created.
            DB::table('influencer_wallets')->updateOrInsert(
                ['influencer_id' => $influencer->id],
                [
                    'balance' => 0,
                    'total_earned' => 0,
                    'total_withdrawn' => 0,
                    'updated_at' => now(),
                    'created_at' => $influencer->created_at ?? now(),
                ]
            );

            // Give each influencer a deterministic selection of products and commission rates.
            $assignedProducts = $productIds
                ->slice(($index * 2) % max($productIds->count(), 1), 4)
                ->values();

            foreach ($assignedProducts as $offset => $productId) {
                DB::table('influencer_product')->updateOrInsert(
                    [
                        'influencer_id' => $influencer->id,
                        'product_id' => $productId,
                    ],
                    [
                        'commission_percentage' => 5 + (($index + $offset) % 4),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $code = sprintf('DEMO-INF%02d-P%03d', $index + 1, $productId);
                DB::table('promotional_links')->updateOrInsert(
                    ['code' => $code],
                    [
                        'influencer_id' => $influencer->id,
                        'product_id' => $productId,
                        'full_url' => 'https://example.test/ref/' . $code,
                        'clicks' => 0,
                        'conversions' => 0,
                        'is_active' => true,
                        'expires_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
