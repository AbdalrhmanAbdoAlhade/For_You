<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('post_id')->constrained('influencer_posts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'post_id'], 'influencer_post_likes_unique');
            $table->index('post_id', 'influencer_post_likes_post_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_post_likes');
    }
};