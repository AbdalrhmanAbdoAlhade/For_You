<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influencer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('commission_percentage', 5, 2)->nullable(); // نسبة خاصة بهذا المنتج (اختياري)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['influencer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_product');
    }
};
