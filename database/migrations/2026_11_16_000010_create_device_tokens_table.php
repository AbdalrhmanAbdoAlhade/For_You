<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // FCM tokens ~163 chars. 191 = safe for utf8mb4 unique index (764 bytes < 767)
            $table->string('token', 191)->unique();

            $table->enum('platform', ['ios', 'android', 'web'])->default('android');
            $table->string('device_name', 100)->nullable();

            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'platform'], 'device_tokens_user_platform_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
