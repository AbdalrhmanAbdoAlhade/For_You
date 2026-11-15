<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotional_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influencer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete(); // لو null = رابط عام
            $table->string('code')->unique();           // الكود الفريد للرابط
            $table->string('full_url');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('conversions')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotional_links');
    }
};
