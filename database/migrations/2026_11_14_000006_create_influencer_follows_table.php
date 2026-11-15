<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();        // المتابِع
            $table->foreignId('influencer_id')->constrained('users')->cascadeOnDelete();  // المتابَع
            $table->timestamps();

            $table->unique(['user_id', 'influencer_id'], 'influencer_follows_unique');
            $table->index('influencer_id', 'influencer_follows_influencer_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_follows');
    }
};