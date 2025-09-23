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
        // 勤怠テーブル
        Schema::create('attendances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('work_date');
            $t->dateTime('clock_in_at')->nullable();
            $t->dateTime('clock_out_at')->nullable();
            $t->text('note')->nullable();
            $t->timestamps();

            $t->unique(['user_id', 'work_date']); // ユーザー×日付をユニークに
        });

        // 休憩テーブル
        Schema::create('attendance_breaks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $t->dateTime('start_time')->nullable();
            $t->dateTime('end_time')->nullable();
            $t->integer('minutes')->nullable(); // 分単位の休憩時間（計算して保存しておく）
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_breaks');
        Schema::dropIfExists('attendances');
    }
};
