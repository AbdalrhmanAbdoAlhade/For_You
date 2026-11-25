<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('governorate', 100)->nullable()->after('phone');
            $table->string('street_address', 500)->nullable()->after('city');
            $table->string('building', 100)->nullable()->after('street_address');
            $table->string('floor', 50)->nullable()->after('building');
            $table->string('apartment', 50)->nullable()->after('floor');
            $table->decimal('latitude', 10, 7)->nullable()->after('apartment');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn([
                'governorate',
                'street_address',
                'building',
                'floor',
                'apartment',
                'latitude',
                'longitude',
            ]);
        });
    }
};