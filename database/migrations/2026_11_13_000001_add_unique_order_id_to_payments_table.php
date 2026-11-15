<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سجل دفع واحد فقط لكل طلب.
        // لو فيه تكرار قديم: نحتفظ بالأهم (paid ثم pending ثم refunded ثم failed)
        // والأحدث عند التساوي، ونحذف الباقي.
        $duplicateOrderIds = DB::table('payments')
            ->select('order_id')
            ->groupBy('order_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('order_id');

        foreach ($duplicateOrderIds as $orderId) {
            $keepId = DB::table('payments')
                ->where('order_id', $orderId)
                ->orderByRaw("CASE status WHEN 'paid' THEN 0 WHEN 'pending' THEN 1 WHEN 'refunded' THEN 2 ELSE 3 END")
                ->orderByDesc('id')
                ->value('id');

            DB::table('payments')
                ->where('order_id', $orderId)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
        });
    }
};
