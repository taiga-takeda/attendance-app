<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stamp_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained('attendance_records')->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained('users')->cascadeOnDelete(); // 申請者
            $table->time('proposed_clock_in');
            $table->time('proposed_clock_out');
            $table->string('proposed_comment');
            $table->tinyInteger('status')->default(0); // 0:承認待ち, 1:承認済み
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete(); // 承認した管理者（削除時はNULL化）
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stamp_correction_requests');
    }
};
