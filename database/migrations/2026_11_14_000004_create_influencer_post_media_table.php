<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('influencer_posts')->cascadeOnDelete();
            $table->string('image');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['post_id', 'sort_order'], 'influencer_post_media_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_post_media');
    }
};