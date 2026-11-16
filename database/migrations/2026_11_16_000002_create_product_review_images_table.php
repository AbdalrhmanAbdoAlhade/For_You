<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_review_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('product_reviews')->cascadeOnDelete();
            $table->string('image');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['review_id', 'sort_order'], 'product_review_images_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_review_images');
    }
};