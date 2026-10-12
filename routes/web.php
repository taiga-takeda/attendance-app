<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceRecordController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ゲスト用ルート（未認証でもアクセス可能）
Route::middleware(['guest'])->group(function () {
    // Fortifyが自動生成する以下のルートと紐付きます
});

// ==================================================
//【内訳4・内訳5対応】メール認証 誘導画面 ＆ 再送処理ルート
// ==================================================
Route::middleware(['auth'])->group(function () {
    // 💡 【内訳4】メール認証誘導画面（Fortify標準の /email/verify へのGETアクセスを受け止めます）
    Route::get('/email/verify', function () {
        return view('auth.verify-email');
    })->name('verification.notice');

    // 💡 【内訳5】認証メール再送機能（提供Bladeの再送ボタンの送信先、および再送ルーティング名を完全バインド）
    Route::post('/email/verification-notification', function (\Illuminate\Http\Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return back()->with('status', 'verification-link-sent');
    })->name('verification.send');
});

// ==================================================
// 1. 【一般専用グループ】認証必須 ＆ 【メール認証（verified）義務化】
// ==================================================
// 一般ユーザーがログインした後の「打刻画面」や「出勤簿」にだけ、確実にメール認証を義務付け
Route::middleware(['auth', 'verified'])->group(function () {

    // 打刻画面の表示（GET）
    Route::get('/attendance', [AttendanceRecordController::class, 'index'])->name('attendance');

    // すべての打刻ボタン（出勤・退勤・休憩）がこの1つのPOSTパスに集約
    Route::post('/attendance', [AttendanceRecordController::class, 'store'])->name('attendance.store');

    // 勤怠一覧画面の表示（GET） [FN023]
    Route::get('/attendance/list', [AttendanceRecordController::class, 'list'])->name('attendance.list');
    // ==========================================
    //【内訳1対応】一般ユーザー専用：マイ勤怠レポート画面の表示（GET）
    // ==========================================
    Route::get('/attendance/report', [\App\Http\Controllers\AttendanceReportController::class, 'index'])->name('attendance.report');

});

// ==================================================
// 2. 【共通・管理者用グループ】認証必須
// ==================================================
// URL共通の出し分けクロージャや、管理者用画面はここに配置することで、管理者がメール認証エラーで弾かれるのを防きます
Route::middleware(['auth'])->group(function () {
    // ログアウト機能を自作コントローラーから、Fortify標準の安全な処理へと完全移行 [FN005, FN016]
    Route::post('/logout', [\Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class, 'destroy'])->name('logout');
    // ==========================================
    // 勤怠詳細画面の表示（GET） [FN025, FN028]
    // ==========================================
    Route::get('/attendance/{id}', function ($id) {
        if (auth()->user() && auth()->user()->admin_status) {
            // 管理者としてログインしている場合は、管理者用の詳細画面を表示 [FN028]
            return app(\App\Http\Controllers\Admin\AdminAttendanceController::class)->showDetail($id);
        }
        // 一般ユーザーの場合は、一般用の詳細画面を表示 [FN025]
        return app(\App\Http\Controllers\AttendanceRecordController::class)->detail($id);
    })->name('attendance.detail');

    // ==========================================
    // 勤怠データの保存・修正申請（POST） [FN026, FN028]
    // ==========================================
    Route::post('/attendance/{id}', function (\App\Http\Requests\StampCorrectionRequest $request, $id) {
        if (auth()->user() && auth()->user()->admin_status) {
            // 管理者の場合は、スタッフの打刻データを直接書き換えて保存（マイルストーン7） [FN028]
            return app(\App\Http\Controllers\Admin\AdminAttendanceController::class)->updateAttendance($request, $id);
        }
        // 一般ユーザーの場合は、自分自身の修正申請を保存（マイルストーン6） [FN026]
        return app(\App\Http\Controllers\AttendanceRecordController::class)->update($request, $id);
    })->name('attendance.update');

    // ==========================================
    // 申請一覧画面の表示（GET） [FN027, FN030]
    // ==========================================
    Route::get('/stamp_correction_request/list', function (\Illuminate\Http\Request $request) {
        if (auth()->user() && auth()->user()->admin_status) {
            return app(\App\Http\Controllers\Admin\AdminAttendanceController::class)->showApplicationList($request);
        }
        return app(\App\Http\Controllers\AttendanceRecordController::class)->applicationList($request);
    })->name('attendance.application-list');

    // 日次勤怠一覧画面の表示（GET） [FN028, FN029]
    Route::get('/admin/attendance/list', [\App\Http\Controllers\Admin\AdminAttendanceController::class, 'showDailyList'])->name('admin.attendance.daily-list');

    // 管理者側からスタッフの勤怠詳細を見る画面（GET） [FN028]
    Route::get('/admin/attendance/{id}', [\App\Http\Controllers\Admin\AdminAttendanceController::class, 'showDetail'])->name('admin.attendance.detail');

    // ==========================================
    // 管理者専用 申請承認ルート 兼 一般ユーザー申請詳細ルート（マイルストーン8） [FN025, FN030]
    // ==========================================
    Route::get('/stamp_correction_request/approve/{id}', function ($id) {
        if (auth()->user() && auth()->user()->admin_status) {
            // 管理者の場合は、管理者専用の「申請承認画面（PG10）」を表示 [FN030]
            return app(\App\Http\Controllers\Admin\AdminAttendanceController::class)->showApplicationDetail($id);
        }

        // 一般ユーザーの場合は、申請データから「大元の勤怠ID」を逆引きして、承認待ちロックがかかった打刻詳細画面（PG05）を表示させます [FN025]
        $application = \App\Models\StampCorrectionRequest::findOrFail($id);
        return app(\App\Http\Controllers\AttendanceRecordController::class)->detail($application->attendance_record_id);
    })->name('admin.application.detail');

    Route::post('/stamp_correction_request/approve/{id}', [\App\Http\Controllers\Admin\AdminAttendanceController::class, 'approveApplication'])->name('admin.application.approve');

    // ==========================================
    // 申請詳細・承認画面の表示（GET） [FN025, FN030]
    // ==========================================
    Route::get('/application/{id}', function ($id) {
        if (auth()->user() && auth()->user()->admin_status) {
            // 管理者の場合は、管理者専用の「申請承認画面（PG10）」を表示 [FN030]
            return app(\App\Http\Controllers\Admin\AdminAttendanceController::class)->showApplicationDetail($id);
        }

        // 一般ユーザーの場合は、申請データから「大元の勤怠ID」を逆引きして、承認待ちロックがかかった打刻詳細画面（PG05）を表示させます [FN025]
        $application = \App\Models\StampCorrectionRequest::findOrFail($id);
        return app(\App\Http\Controllers\AttendanceRecordController::class)->detail($application->attendance_record_id);
    })->name('admin.application.detail');

    // ==========================================
    // マイルストーン8：スタッフ一覧画面（管理者用） [FN028]
    // ==========================================
    Route::get('/admin/staff', [\App\Http\Controllers\Admin\AdminAttendanceController::class, 'showStaffList'])->name('admin.staff.list');
    Route::get('/admin/staff/list', [\App\Http\Controllers\Admin\AdminAttendanceController::class, 'showStaffList']);
    Route::get('/admin/users', [\App\Http\Controllers\Admin\AdminAttendanceController::class, 'showStaffList']);
    Route::get('/admin/user', [\App\Http\Controllers\Admin\AdminAttendanceController::class, 'showStaffList']);

    // ==========================================
    // 管理者専用 スタッフごとの月次勤怠一覧ルート（マイルストーン8） [FN028]
    // ==========================================
    Route::get('/admin/attendance/staff/{id}', [\App\Http\Controllers\Admin\AdminAttendanceController::class, 'showStaffMonthlyList'])->name('admin.attendance.staff-list');
    Route::post('/export', [\App\Http\Controllers\Admin\AdminCsvExportController::class, 'exportStaffMonthlyCsv'])->name('admin.attendance.staff-csv');
});

// =================---------------------------------
// 3. 管理者専用ログイン・ログアウトルート [FN016]
// =================---------------------------------
Route::get('/admin/login', function () {
    return view('admin.admin-login'); // 提供されている管理者用ログインBladeを表示 [FN016]
})->name('admin.login');

Route::post('/admin/login', [\Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class, 'store']);
Route::post('/admin/logout', [\Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class, 'destroy']);
