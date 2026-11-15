<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('influencer_commissions', function (Blueprint $table) {
            $table->timestamp('available_at')->nullable()->after('status');
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::table('influencer_commissions', function (Blueprint $table) {
            $table->dropIndex(['status', 'available_at']);
            $table->dropColumn('available_at');
        });
    }
};
