<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_histories', function (Blueprint $table) {
            $table->id();

            // null = guest (لكن بنسجّل عشان trending)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('query', 100);

            // all | products | brands | creators
            $table->string('type', 20)->default('all');

            $table->unsignedInteger('results_count')->default(0);

            $table->timestamp('created_at')->useCurrent();

            // trending: count per query within date range
            $table->index(['query', 'created_at'], 'search_histories_query_time_idx');
            // recent: last searches for a user
            $table->index(['user_id', 'created_at'], 'search_histories_user_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_histories');
    }
};
