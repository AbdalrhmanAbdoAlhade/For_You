<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // handle فريد. nullable عشان المؤثرين القدام ممكن ميكونش عندهم
            $table->string('handle', 50)->nullable()->unique();

            $table->text('bio')->nullable();
            $table->json('social_links')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_profiles');
    }
};
