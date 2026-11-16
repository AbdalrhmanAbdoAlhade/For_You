<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('status', 50);
            $table->string('previous_status', 50)->nullable();

            // العميل أو الأدمن اللي عمل التغيير. NULL للتغييرات التلقائية (system/webhook)
            $table->foreignId('changed_by')->nullable()
                ->constrained('users')->nullOnDelete();

            // note يظهر للعميل، internal_note للأدمن فقط
            $table->string('note', 500)->nullable();
            $table->text('internal_note')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at'], 'order_status_histories_order_time_idx');
            $table->index('status', 'order_status_histories_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};