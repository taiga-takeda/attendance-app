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
        Schema::table('users', function (Blueprint $blueprint) {
            // 管理者フラグを追記（デフォルトは一般ユーザー: false）
            $blueprint->boolean('admin_status')->default(false)->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $blueprint) {
            // ロールバック（元に戻す）時のために削除処理を定義
            $blueprint->dropColumn('admin_status');
        });
    }
};
