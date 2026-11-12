<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('admin_note')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('paid_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->after('paid_by');
            $table->foreignId('processed_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            $table->string('transfer_reference', 100)->nullable()->after('processed_by'); // رقم عملية التحويل الفعلي
        });
    }

    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('paid_by');
            $table->dropConstrainedForeignId('processed_by');
            $table->dropColumn(['approved_at', 'paid_at', 'transfer_reference']);
        });
    }
};
