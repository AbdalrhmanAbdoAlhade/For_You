<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::table('products', function (Blueprint $table) {
        $table->string('name_en')->nullable()->after('name');
        $table->text('description_en')->nullable()->after('description');
        $table->text('short_description_en')->nullable()->after('short_description');
    });
    Schema::table('categories', function (Blueprint $table) {
        $table->string('name_en')->nullable()->after('name');
        $table->text('description_en')->nullable()->after('description');
    });
    Schema::table('banners', function (Blueprint $table) {
        $table->string('title_en')->nullable()->after('title');
        $table->string('subtitle_en')->nullable()->after('subtitle');
    });
}

public function down(): void
{
    Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['name_en', 'description_en', 'short_description_en']));
    Schema::table('categories', fn (Blueprint $t) => $t->dropColumn(['name_en', 'description_en']));
    Schema::table('banners', fn (Blueprint $t) => $t->dropColumn(['title_en', 'subtitle_en']));
}
};
