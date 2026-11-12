<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) carts.user_id unique (سلة واحدة لكل مستخدم)
        // لو فيه تكرار قديم، نسيب أقدم سلة ونحذف الباقي
        $dupCarts = DB::table('carts')
            ->select('user_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupCarts as $row) {
            DB::table('carts')
                ->where('user_id', $row->user_id)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }

        Schema::table('carts', function (Blueprint $table) {
            $table->unique('user_id');
        });

        // 2) shippings.order_id unique
        $dupShippings = DB::table('shippings')
            ->select('order_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('order_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupShippings as $row) {
            DB::table('shippings')
                ->where('order_id', $row->order_id)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }

        Schema::table('shippings', function (Blueprint $table) {
            $table->unique('order_id');
        });

        // 3) influencer_commissions.order_item_id unique (لو مش null)
        $dupCommissions = DB::table('influencer_commissions')
            ->whereNotNull('order_item_id')
            ->select('order_item_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('order_item_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupCommissions as $row) {
            DB::table('influencer_commissions')
                ->where('order_item_id', $row->order_item_id)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }

        Schema::table('influencer_commissions', function (Blueprint $table) {
            $table->unique('order_item_id');
        });

        // 4) تحويل cascadeOnDelete لـ restrict على الجداول المالية الحساسة
        // orders.customer_id
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')->on('users')
                ->restrictOnDelete();
        });

        // order_items.product_id — لو موجود cascade
        if (Schema::hasColumn('order_items', 'product_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
            });
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('product_id')
                    ->references('id')->on('products')
                    ->nullOnDelete(); // المنتج يتشال، الصنف يفضل للتاريخ
            });
        }

        // influencer_commissions.order_id
        Schema::table('influencer_commissions', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
        Schema::table('influencer_commissions', function (Blueprint $table) {
            $table->foreign('order_id')
                ->references('id')->on('orders')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });

        Schema::table('shippings', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
        });

        Schema::table('influencer_commissions', function (Blueprint $table) {
            $table->dropUnique(['order_item_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });

        if (Schema::hasColumn('order_items', 'product_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
            });
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('product_id')
                    ->references('id')->on('products')
                    ->cascadeOnDelete();
            });
        }

        Schema::table('influencer_commissions', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
        Schema::table('influencer_commissions', function (Blueprint $table) {
            $table->foreign('order_id')
                ->references('id')->on('orders')
                ->cascadeOnDelete();
        });
    }
};
