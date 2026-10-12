<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposed_rest_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stamp_correction_request_id')->constrained('stamp_correction_requests')->cascadeOnDelete();
            $table->foreignId('rest_record_id')->nullable()->constrained('rest_records')->nullOnDelete(); // 新規追加時は空なのでnullable
            $table->time('proposed_break_in');
            $table->time('proposed_break_out');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposed_rest_records');
    }
};
