<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::create('product_variants', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('size', 50)->nullable();
            $table->string('color', 50)->nullable();

            $table->string('sku', 100)->nullable()->unique();

            $table->unsignedInteger('stock')->default(0);

            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('discount_price', 10, 2)->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            if ($driver === 'sqlite') {
                $table->string('combination_key')->storedAs(
                    "COALESCE(size, '') || '|' || COALESCE(color, '')"
                );
            } else {
                $table->char('combination_key', 32)->storedAs(
                    "MD5(CONCAT(COALESCE(size, ''), '|', COALESCE(color, '')))"
                );
            }

            $table->unique(['product_id', 'combination_key'], 'product_variants_product_combination_unique');
            $table->index(['product_id', 'is_active', 'sort_order'], 'product_variants_feed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
