<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // nullable للتوافق مع الطلبات القديمة — null = طلب قديم
            $table->string('shipping_method', 20)->nullable()->after('shipping_cost');
            $table->decimal('cod_fee', 10, 2)->default(0)->after('shipping_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_method', 'cod_fee']);
        });
    }
};
