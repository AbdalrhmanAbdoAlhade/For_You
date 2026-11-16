<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlite') {
            $indexes = DB::select("
                SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'cart_items'
                  AND NON_UNIQUE = 0
                  AND INDEX_NAME != 'PRIMARY'
                GROUP BY INDEX_NAME
            ");

            foreach ($indexes as $idx) {
                if (in_array($idx->cols, ['cart_id,product_id', 'product_id,cart_id'], true)) {
                    Schema::table('cart_items', function (Blueprint $table) use ($idx) {
                        $table->dropUnique($idx->INDEX_NAME);
                    });
                }
            }
        }

        Schema::table('cart_items', function (Blueprint $table) use ($driver) {
            if ($driver === 'sqlite') {
                $table->string('variant_key')->storedAs(
                    "COALESCE(CAST(variant_id AS TEXT), '_null_')"
                );
            } else {
                $table->char('variant_key', 32)->storedAs(
                    "MD5(COALESCE(CAST(variant_id AS CHAR), '_null_'))"
                )->after('variant_id');
            }
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(
                ['cart_id', 'product_id', 'variant_key'],
                'cart_items_cart_product_variant_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_cart_product_variant_unique');
            $table->dropColumn('variant_key');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['cart_id', 'product_id'], 'cart_items_cart_id_product_id_unique');
        });
    }
};
