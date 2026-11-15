<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_story_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('story_id')->constrained('influencer_stories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'story_id'], 'influencer_story_likes_unique');
            $table->index('story_id', 'influencer_story_likes_story_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_story_likes');
    }
};