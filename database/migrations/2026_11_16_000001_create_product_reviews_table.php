<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // الأوردر اللي اتحقق منه الشراء (لو موجود)
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedTinyInteger('rating');          // 1..5 (بيتحقق منه في الـ Request)
            $table->text('body')->nullable();

            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_creator')->default(false);  // كاتب التقييم مؤثر (فلتر "Creators")
            $table->boolean('is_active')->default(true);    // الأدمن يقدر يخفيه

            $table->timestamps();

            // تقييم واحد لكل مستخدم على كل منتج
            $table->unique(['user_id', 'product_id'], 'product_reviews_user_product_unique');

            // قائمة تقييمات منتج (الأحدث أولاً)
            $table->index(['product_id', 'is_active', 'created_at'], 'product_reviews_product_feed_index');
            // توزيع النجوم وفلتر الـ rating
            $table->index(['product_id', 'is_active', 'rating'], 'product_reviews_product_rating_index');
            // تقييمات مستخدم/مؤثر معين (tab Reviews في بروفايل الكريتور)
            $table->index(['user_id', 'is_active', 'created_at'], 'product_reviews_user_feed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};