<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_story_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained('influencer_stories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('viewed_at')->useCurrent();

            // مشاهدة واحدة لكل (قصة، مستخدم): بنعرف منها الحلقة الذهبية (لسه ما شافهاش) وعدد المشاهدات
            $table->unique(['story_id', 'user_id'], 'influencer_story_views_unique');
            $table->index('user_id', 'influencer_story_views_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_story_views');
    }
};