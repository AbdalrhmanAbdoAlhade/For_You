<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();   // المتابِع
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete(); // المتابَع
            $table->timestamps();

            $table->unique(['user_id', 'brand_id'], 'brand_follows_unique');
            $table->index('brand_id', 'brand_follows_brand_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_follows');
    }
};
