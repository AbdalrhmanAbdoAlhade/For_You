<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influencer_id')->constrained('users')->cascadeOnDelete();
            $table->string('image');
            $table->text('caption')->nullable();
            $table->string('label', 60)->nullable();                       // زي "WINTER LAYERS"
            $table->unsignedTinyInteger('duration_seconds')->default(5);   // مدة العرض قبل الانتقال للي بعده
            $table->boolean('is_active')->default(true);                   // إخفاء يدوي قبل الانتهاء
            $table->timestamp('expires_at');                               // created_at + 24 ساعة
            $table->timestamps();

            $table->index(['influencer_id', 'expires_at'], 'influencer_stories_influencer_index');
            $table->index('expires_at', 'influencer_stories_expires_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_stories');
    }
};