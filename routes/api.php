<?php

use App\Http\Controllers\Api\V1\AttendanceRecordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| 💡 【マイルストーン13：公開APIエンドポイント完全大開通ルート定義】
|
*/

// ==================================================
// 🌐 【仕様書適合】 1. 読み取り系エンドポイント（認証不要・認可不要）
// ==================================================
// 💡 仕様書の指定通り、外部から誰でも自由に勤怠の一覧・詳細データをJSON取得できるエンドポイントです
Route::get('/v1/attendance-records', [AttendanceRecordController::class, 'index']);
Route::get('/v1/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'show']);

// ==================================================
// 🌐 【仕様書適合】 2. 書き込み系エンドポイント（Sanctumトークン認証必須グループ）
// ==================================================
// 💡 仕様書（AP06）に明記されている通り、登録・更新・削除は auth:sanctum ミドルウェアで強固にルート保護します
Route::middleware('auth:sanctum')->group(function () {

    // AP03: 勤怠新規登録（POST）➔ 201 Created 返却仕様
    Route::post('/v1/attendance-records', [AttendanceRecordController::class, 'store']);

    // AP04: 勤怠更新（PUT/PATCH）➔ Policy認可連携・403制限仕様
    Route::put('/v1/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'update']);
    Route::patch('/v1/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'update']);

    // AP05: 勤怠削除（DELETE）➔ Policy認可連携・204 No Content 返却仕様
    Route::delete('/v1/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'destroy']);

});
