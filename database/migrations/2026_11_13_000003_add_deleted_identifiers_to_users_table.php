<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // عند حذف مستخدم (soft delete) نحرر الإيميل/الهاتف للاستخدام مرة أخرى،
        // ونحتفظ بالقيم الأصلية هنا للمراجعة والدعم.
        Schema::table('users', function (Blueprint $table) {
            $table->string('deleted_email')->nullable();
            $table->string('deleted_phone')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['deleted_email', 'deleted_phone']);
        });
    }
};
