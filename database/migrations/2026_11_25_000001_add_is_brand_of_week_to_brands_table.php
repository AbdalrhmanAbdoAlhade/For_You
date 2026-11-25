<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->boolean('is_brand_of_week')->default(false)->after('is_active');
            $table->index('is_brand_of_week', 'brands_brand_of_week_index');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropIndex('brands_brand_of_week_index');
            $table->dropColumn('is_brand_of_week');
        });
    }
};