<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فهارس أداء لصفحات المتجر العامة (الرئيسية/المنتجات).
 * كل استعلامات المنتجات بتفلتر على is_active + is_featured/created_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('is_active', 'products_is_active_index');
            $table->index(['is_active', 'is_featured'], 'products_active_featured_index');
            $table->index(['is_active', 'created_at'], 'products_active_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_is_active_index');
            $table->dropIndex('products_active_featured_index');
            $table->dropIndex('products_active_created_at_index');
        });
    }
};
