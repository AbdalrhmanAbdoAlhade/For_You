<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->nullOnDelete();

            $table->string('variant_size', 50)->nullable()->after('variant_id');
            $table->string('variant_color', 50)->nullable()->after('variant_size');
            $table->string('variant_sku', 100)->nullable()->after('variant_color');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['variant_id']);
            $table->dropColumn(['variant_id', 'variant_size', 'variant_color', 'variant_sku']);
        });
    }
};