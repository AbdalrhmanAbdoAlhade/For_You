<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // ========================
        // 1. الصلاحيات (Permissions)
        // ========================
        $permissions = [

            // ========== Products ==========
            ['name' => 'View Products',     'slug' => 'products.view',     'group' => 'products'],
            ['name' => 'Create Products',   'slug' => 'products.create',   'group' => 'products'],
            ['name' => 'Edit Products',     'slug' => 'products.edit',     'group' => 'products'],
            ['name' => 'Delete Products',   'slug' => 'products.delete',   'group' => 'products'],

            // ========== Categories ==========
            ['name' => 'View Categories',   'slug' => 'categories.view',   'group' => 'categories'],
            ['name' => 'Create Categories', 'slug' => 'categories.create', 'group' => 'categories'],
            ['name' => 'Edit Categories',   'slug' => 'categories.edit',   'group' => 'categories'],
            ['name' => 'Delete Categories', 'slug' => 'categories.delete', 'group' => 'categories'],

            // ========== Orders ==========
            ['name' => 'View Orders',       'slug' => 'orders.view',       'group' => 'orders'],
            ['name' => 'Update Orders',     'slug' => 'orders.update',     'group' => 'orders'],
            ['name' => 'Manage Orders',     'slug' => 'orders.manage',     'group' => 'orders'],
            ['name' => 'Cancel Orders',     'slug' => 'orders.cancel',     'group' => 'orders'],

            // ========== Customers ==========
            ['name' => 'View Customers',    'slug' => 'customers.view',    'group' => 'customers'],
            ['name' => 'Manage Customers',  'slug' => 'customers.manage',  'group' => 'customers'],

            // ========== Influencers ==========
            ['name' => 'View Influencers',          'slug' => 'influencers.view',          'group' => 'influencers'],
            ['name' => 'Create Influencers',        'slug' => 'influencers.create',        'group' => 'influencers'],
            ['name' => 'Edit Influencers',          'slug' => 'influencers.edit',          'group' => 'influencers'],
            ['name' => 'Delete Influencers',        'slug' => 'influencers.delete',        'group' => 'influencers'],
            ['name' => 'Manage Influencer Products','slug' => 'influencers.products',      'group' => 'influencers'],
            ['name' => 'Manage Promotional Links',  'slug' => 'influencers.links',         'group' => 'influencers'],
            ['name' => 'View Influencer Wallets',   'slug' => 'influencers.wallets',       'group' => 'influencers'],
            ['name' => 'Manage Influencer Commissions', 'slug' => 'influencers.commissions', 'group' => 'influencers'],
            
            // ========== Withdrawals (New) ==========
            ['name' => 'View Withdrawals',          'slug' => 'withdrawals.view',          'group' => 'influencers'],
            ['name' => 'Approve Withdrawals',       'slug' => 'withdrawals.approve',       'group' => 'influencers'],
            ['name' => 'Pay Withdrawals',           'slug' => 'withdrawals.pay',           'group' => 'influencers'],

            // ========== Payments ==========
            ['name' => 'View Payments',     'slug' => 'payments.view',     'group' => 'payments'],
            ['name' => 'Manage Payments',   'slug' => 'payments.manage',   'group' => 'payments'],
            ['name' => 'Confirm Payments',  'slug' => 'payments.confirm',  'group' => 'payments'],
            ['name' => 'Refund Payments',   'slug' => 'payments.refund',   'group' => 'payments'],

            // ========== Shipping ==========
            ['name' => 'View Shipping',     'slug' => 'shipping.view',     'group' => 'shipping'],
            ['name' => 'Manage Shipping',   'slug' => 'shipping.manage',   'group' => 'shipping'],

            // ========== Employees & Roles ==========
            ['name' => 'View Employees',    'slug' => 'employees.view',    'group' => 'employees'],
            ['name' => 'Create Employees',  'slug' => 'employees.create',  'group' => 'employees'],
            ['name' => 'Edit Employees',    'slug' => 'employees.edit',    'group' => 'employees'],
            ['name' => 'Delete Employees',  'slug' => 'employees.delete',  'group' => 'employees'],
            ['name' => 'Manage Roles',      'slug' => 'roles.manage',      'group' => 'employees'],
            ['name' => 'Manage Permissions','slug' => 'permissions.manage','group' => 'employees'],

            // ========== Reports ==========
            ['name' => 'View Reports',      'slug' => 'reports.view',      'group' => 'reports'],
            ['name' => 'View Sales Reports','slug' => 'reports.sales',     'group' => 'reports'],
            ['name' => 'View Influencer Reports', 'slug' => 'reports.influencers', 'group' => 'reports'],

            // ========== Dashboard & Banners & Coupons ==========
            ['name' => 'View Dashboard',    'slug' => 'dashboard.view',    'group' => 'dashboard'],
            ['name' => 'View Banners',      'slug' => 'banners.view',      'group' => 'banners'],
            ['name' => 'Create Banners',    'slug' => 'banners.create',    'group' => 'banners'],
            ['name' => 'Edit Banners',      'slug' => 'banners.edit',      'group' => 'banners'],
            ['name' => 'Delete Banners',    'slug' => 'banners.delete',    'group' => 'banners'],
            ['name' => 'View Coupons',      'slug' => 'coupons.view',      'group' => 'coupons'],
            ['name' => 'Create Coupons',    'slug' => 'coupons.create',    'group' => 'coupons'],
            ['name' => 'Edit Coupons',      'slug' => 'coupons.edit',      'group' => 'coupons'],
            ['name' => 'Delete Coupons',    'slug' => 'coupons.delete',    'group' => 'coupons'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name'  => $permission['name'],
                    'group' => $permission['group'],
                ]
            );
        }

        // ========================
        // 2. الأدوار (Roles)
        // ========================

        // ----- Super Admin -----
        $superAdmin = Role::updateOrCreate(
            ['slug' => 'super-admin'],
            [
                'name'        => 'Super Admin',
                'description' => 'يمتلك جميع الصلاحيات في النظام',
                'is_active'   => true,
            ]
        );

        // إعطاء كل الصلاحيات للـ Super Admin
        $superAdmin->permissions()->sync(Permission::pluck('id'));

        // ----- Products Manager -----
        $productsManager = Role::updateOrCreate(
            ['slug' => 'products-manager'],
            [
                'name'        => 'Products Manager',
                'description' => 'مسؤول عن إدارة المنتجات والأقسام',
                'is_active'   => true,
            ]
        );

        $productsManager->permissions()->sync(
            Permission::whereIn('slug', [
                'dashboard.view',
                'products.view',
                'products.create',
                'products.edit',
                'products.delete',
                'categories.view',
                'categories.create',
                'categories.edit',
                'categories.delete',
            ])->pluck('id')
        );

        // ----- Orders Manager -----
        $ordersManager = Role::updateOrCreate(
            ['slug' => 'orders-manager'],
            [
                'name'        => 'Orders Manager',
                'description' => 'مسؤول عن إدارة الطلبات والشحن',
                'is_active'   => true,
            ]
        );

        $ordersManager->permissions()->sync(
            Permission::whereIn('slug', [
                'dashboard.view',
                'orders.view',
                'orders.update',
                'orders.manage',
                'orders.cancel',
                'shipping.view',
                'shipping.manage',
                'customers.view',
            ])->pluck('id')
        );

        // ----- Influencers Manager -----
        $influencersManager = Role::updateOrCreate(
            ['slug' => 'influencers-manager'],
            [
                'name'        => 'Influencers Manager',
                'description' => 'مسؤول عن إدارة المؤثرين والمحافظ والعمولات',
                'is_active'   => true,
            ]
        );

        $influencersManager->permissions()->sync(
            Permission::whereIn('slug', [
                'dashboard.view',
                'influencers.view',
                'influencers.create',
                'influencers.edit',
                'influencers.delete',
                'influencers.products',
                'influencers.links',
                'influencers.wallets',
                'influencers.commissions',
                'reports.influencers',
                'withdrawals.view', // تمت إضافتها بناءً على التحديثات
            ])->pluck('id')
        );

        // ----- Finance Manager -----
        $financeManager = Role::updateOrCreate(
            ['slug' => 'finance-manager'],
            [
                'name'        => 'Finance Manager',
                'description' => 'مسؤول عن المدفوعات والتقارير المالية',
                'is_active'   => true,
            ]
        );

        $financeManager->permissions()->sync(
            Permission::whereIn('slug', [
                'dashboard.view',
                'payments.view',
                'payments.manage',
                'payments.confirm', // تمت إضافتها بناءً على التحديثات
                'reports.view',
                'reports.sales',
                'influencers.wallets',
                'influencers.commissions',
            ])->pluck('id')
        );

        // ========================
        // 3. تطبيق ربط الصلاحيات التلقائي (Dynamic Rol-Permission mapping)
        // ========================
        
        // الأدوار اللي كانت بتشوف المحافظ تشوف السحوبات بس
        DB::statement("
            INSERT IGNORE INTO role_permission (role_id, permission_id, created_at, updated_at)
            SELECT rp.role_id, pn.id, NOW(), NOW()
            FROM role_permission rp
            JOIN permissions po ON po.id = rp.permission_id AND po.slug = 'influencers.wallets'
            JOIN permissions pn ON pn.slug = 'withdrawals.view'
        ");

        // الأدوار اللي كانت payments.manage تاخد تأكيد الدفع بس
        DB::statement("
            INSERT IGNORE INTO role_permission (role_id, permission_id, created_at, updated_at)
            SELECT rp.role_id, pn.id, NOW(), NOW()
            FROM role_permission rp
            JOIN permissions po ON po.id = rp.permission_id AND po.slug = 'payments.manage'
            JOIN permissions pn ON pn.slug = 'payments.confirm'
        ");

        // ========================
        // 4. إنشاء Super Admin User (اختياري)
        // ========================
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => 'Super Admin',
                'phone'    => '01000000000',
                'password' => Hash::make('password'),
                'type'     => 'admin',
                'is_active'=> true,
            ]
        );

        // ربط الـ Super Admin بالرول
        $admin->roles()->sync([$superAdmin->id]);
    }
}
