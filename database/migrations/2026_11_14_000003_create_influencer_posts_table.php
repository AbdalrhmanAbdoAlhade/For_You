<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influencer_id')->constrained('users')->cascadeOnDelete();
            $table->text('caption')->nullable();
            $table->string('label', 60)->nullable();      // زي "WINTER LAYERS"
            $table->boolean('is_active')->default(true);  // بيتنشر مباشرة، والأدمن/المؤثر يقدر يخفيه
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'published_at'], 'influencer_posts_feed_index');
            $table->index(['influencer_id', 'published_at'], 'influencer_posts_influencer_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_posts');
    }
};