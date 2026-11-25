<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('influencer_profiles', function (Blueprint $table) {
            // URL كامل لصورة الغلاف (مخزّن مثل avatar: URL من Storage::url)
            $table->string('cover_image', 2048)->nullable()->after('bio');

            // رابط إنستغرام (URL خارجي)
            $table->string('instagram_url', 500)->nullable()->after('cover_image');
        });
    }

    public function down(): void
    {
        Schema::table('influencer_profiles', function (Blueprint $table) {
            $table->dropColumn(['cover_image', 'instagram_url']);
        });
    }
};