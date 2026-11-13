<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ترحيل تصحيحي للتثبيتات اللي شغّلت 2026_11_12_000003 قبل إصلاح الـ nullable.
 *
 * الـ FK الجديد على order_items.product_id بيستخدم nullOnDelete، وده بيتطلب
 * إن العمود يقبل NULL. على MySQL، محاولة إنشاء FK بـ SET NULL على عمود
 * NOT NULL بترجع خطأ 1830. الـ migration الأصلية اتصلحت، وده بيغطي قواعد
 * البيانات اللي الـ migration القديمة اتنفذت عليها بالفعل.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('order_items', 'product_id')) {
            return;
        }

        $column = collect(Schema::getColumns('order_items'))
            ->firstWhere('name', 'product_id');

        // لو العمود بالفعل nullable، مفيش حاجة نعملها.
        if (!$column || ($column['nullable'] ?? false)) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        /*
         * لا نرجّع NOT NULL تلقائيًا: ممكن توجد أصناف بمنتج محذوف
         * (product_id = null) بعد تفعيل nullOnDelete، والرجوع هيفشل.
         */
    }
};
