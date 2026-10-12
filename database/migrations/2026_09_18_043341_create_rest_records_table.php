<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rest_records', function (Blueprint $table) {
            $table->id();
            // 勤怠レコードIDの外部キー設定 ＋ カスケード削除
            $table->foreignId('attendance_record_id')->constrained('attendance_records')->cascadeOnDelete();
            $table->time('break_in');
            $table->time('break_out')->nullable(); // 休憩戻りまでは空を許容
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rest_records');
    }
};
