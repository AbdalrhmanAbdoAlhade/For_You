<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_story_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained('influencer_stories')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // رابط الترويج بتاع المؤثر للمنتج ده (هو اللي بيتفتح لما العميل يدوس Shop)
            $table->foreignId('promotional_link_id')->nullable()->constrained('promotional_links')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['story_id', 'product_id'], 'influencer_story_products_unique');
            $table->index('product_id', 'influencer_story_products_product_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_story_products');
    }
};