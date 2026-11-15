<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // فهارس للأعمدة المستخدمة في الفلترة والتقارير والـ cron.
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status', 'orders_status_index');
            $table->index('created_at', 'orders_created_at_index');
            $table->index(['status', 'created_at'], 'orders_status_created_at_index');
        });

        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->index('status', 'withdrawal_requests_status_index');
            $table->index(['influencer_id', 'status'], 'withdrawal_requests_influencer_status_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('status', 'payments_status_index');
        });

        Schema::table('influencer_commissions', function (Blueprint $table) {
            $table->index('status', 'influencer_commissions_status_index');
            $table->index(['influencer_id', 'status'], 'influencer_commissions_influencer_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('influencer_commissions', function (Blueprint $table) {
            $table->dropIndex('influencer_commissions_status_index');
            $table->dropIndex('influencer_commissions_influencer_status_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_status_index');
        });

        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropIndex('withdrawal_requests_status_index');
            $table->dropIndex('withdrawal_requests_influencer_status_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_index');
            $table->dropIndex('orders_created_at_index');
            $table->dropIndex('orders_status_created_at_index');
        });
    }
};
