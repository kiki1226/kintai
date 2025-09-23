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
        Schema::table('holidays', function (Blueprint $table) {
            // 祝日の日付カラムを追加
            if (!Schema::hasColumn('holidays', 'holiday_date')) {
                $table->date('holiday_date')->after('id');
            }
            // 任意で祝日名を保存したい場合
            if (!Schema::hasColumn('holidays', 'name')) {
                $table->string('name')->nullable()->after('holiday_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropColumn(['holiday_date', 'name']);
        });
    }
};
