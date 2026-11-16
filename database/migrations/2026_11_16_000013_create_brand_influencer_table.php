<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_influencer', function (Blueprint $table) {
            $table->id();

            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('influencer_id')->constrained('users')->cascadeOnDelete();

            // لو البراند مشترك بين كذا مؤثر (Many-to-Many)
            $table->unique(['brand_id', 'influencer_id'], 'brand_influencer_unique');

            // لو محتاجين فلتر active لواحد منهم
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['influencer_id', 'is_active'], 'brand_influencer_feed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_influencer');
    }
};
