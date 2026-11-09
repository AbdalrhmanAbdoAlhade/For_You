<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('withdrawal_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('influencer_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('wallet_id')->constrained('influencer_wallets')->cascadeOnDelete();
    $table->decimal('amount', 12, 2);
    $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
    $table->string('payment_method')->nullable(); // vodafone_cash, bank, instapay...
    $table->string('account_details')->nullable(); // رقم المحفظة أو الحساب
    $table->text('admin_note')->nullable();
    $table->timestamp('processed_at')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
