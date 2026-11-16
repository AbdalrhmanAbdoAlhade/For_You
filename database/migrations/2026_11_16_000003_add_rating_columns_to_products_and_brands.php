<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('rating_avg', 3, 2)->default(0)->after('is_active');
            $table->unsignedInteger('reviews_count')->default(0)->after('rating_avg');

            $table->index('rating_avg', 'products_rating_avg_index');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->decimal('rating_avg', 3, 2)->default(0)->after('is_active');
            $table->unsignedInteger('reviews_count')->default(0)->after('rating_avg');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['rating_avg', 'reviews_count']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_rating_avg_index');
            $table->dropColumn(['rating_avg', 'reviews_count']);
        });
    }
};