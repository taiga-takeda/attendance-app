<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\RestRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAttendanceController extends Controller
{
    /**
     * 全ユーザーの日次勤怠一覧画面を表示する [FN028]
     */
    public function showDailyList(Request $request): View
    {
        // 1. 提供Bladeの「?date=」パラメータに合わせます [FN029]
        $dateParam = $request->query('date', Carbon::today()->format('Y-m-d'));

        // 提供Bladeの $date->format(...) に対応させるためのCarbonオブジェクト [FN029]
        $date = Carbon::parse($dateParam);

        // 提供Bladeの previousDay / nextDay に引き渡す文字列計算 [FN029]
        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        // 【開発プロセス遵守】Eager Loading (with) を使用して全ユーザーと本日の勤怠・休憩を一括取得（N+1を徹底防止）
        $users = User::where('admin_status', false)
            ->with(['attendanceRecords' => function ($query) use ($dateParam) {
                $query->where('date', $dateParam)->with('restRecords');
            }])
            ->get();

        // 提供Bladeのループ内部で `@if ($attendance)` の判定を行うためのコレクション
        $attendanceRecords = collect();

        foreach ($users ?? [] as $user) {
            $record = $user->attendanceRecords->first();

            if ($record) {$clockIn = Carbon::parse($record->clock_in);$clockOut = $record->clock_out ? Carbon::parse($record->clock_out) : null;

                // 休憩合計時間の計算
                $totalBreakSeconds = 0;
                foreach ($record->restRecords ?? [] as $rest) {
                    if ($rest->break_in && $rest->break_out) {$totalBreakSeconds += Carbon::parse($rest->break_in)->diffInSeconds(Carbon::parse($rest->break_out));
                    }
                }

                $totalBreakTime = null;
                $totalTime = null;

                if ($totalBreakSeconds > 0) {$bHours = floor($totalBreakSeconds / 3600);$bMinutes = floor(($totalBreakSeconds % 3600) / 60);$totalBreakTime = sprintf('%02d:%02d:00', $bHours, $bMinutes); // 提供BladeのCarbon::parseに適合
                } elseif ($record->clock_out) {$totalBreakTime = '00:00:00';
                }

                if ($clockOut) {
                    $staySeconds = $clockIn->diffInSeconds($clockOut);$workSeconds = $staySeconds - $totalBreakSeconds;
                    if ($workSeconds < 0) {$workSeconds = 0;}

                    $wHours = floor($workSeconds / 3600);$wMinutes = floor(($workSeconds % 3600) / 60);$totalTime = sprintf('%02d:%02d:00', $wHours, $wMinutes); // 提供BladeのCarbon::parseに適合
                }

                // 提供Bladeが求めるオブジェクト構造をセット
                $record->total_break_time =$totalBreakTime;
                $record->total_time =$totalTime;

                // 配列アクセスの仕様 $attendance['id'] に適合させるためのプロパティセット
                $record['id'] =$record->id;

                $attendanceRecords->push($record);
            }
        }

        return view('admin.admin-attendance-list', compact(
            'date',
            'previousDay',
            'nextDay',
            'users',
            'attendanceRecords'
        ));
    }
    /**
     * 管理者側：スタッフの勤怠詳細画面を表示する [FN028]
     */
    public function showDetail(int $id): View
    {
        // 休憩レコードもN+1問題を起こさず一括取得
        $attendance = AttendanceRecord::with('restRecords')->findOrFail($id);

        // 提供Bladeが求める「$user」変数（スタッフ情報）を組み立てます [FN028]
        $user = $attendance->user; 

        $carbonDate = Carbon::parse($attendance->date);

        // 休憩レコードを提供Bladeが求める配列構造に変換
        $breaks = [];
        foreach ($attendance->restRecords ?? [] as $rest) {
            $breaks[] = [
                'break_in' => Carbon::parse($rest->break_in)->format('H:i'),
                'break_out' => $rest->break_out ? Carbon::parse($rest->break_out)->format('H:i') : '',
            ];
        }

        // 提供されたBladeの $attendanceRecord[...] の配列仕様と100%一致するようにオブジェクトを組み立てます [FN028]
        $attendanceRecord = [
            'id' => $attendance->id,
            'year' => $carbonDate->format('Y'),
            'date' => $carbonDate->format('m月d日'),
            'clock_in' => Carbon::parse($attendance->clock_in)->format('H:i'),
            'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '',
            'breaks' => $breaks,
            'comment' => '', // 基本機能段階の初期値
        ];

        // 提供Bladeが必要としている変数名（$user, $attendanceRecord）で綺麗に引き渡します [FN028]
        return view('admin.admin-detail', compact('user', 'attendanceRecord'));
    }

    /**
     * 管理者側：スタッフの勤怠データを直接書き換えて上書き保存する [FN028]
     */
    public function updateAttendance(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $attendance = AttendanceRecord::findOrFail($id);

        // 💡 1. 管理者の入力内容で勤怠レコード（親）を直接上書きアップデート [FN028]
        $attendance->update([
            'clock_in' => $request->input('new_clock_in'),
            'clock_out' => $request->input('new_clock_out'),
        ]);

        // 2. 休憩時間（子）の直接上書き更新処理
        $breakIns = $request->input('new_break_in', []);
        $breakOuts = $request->input('new_break_out', []);

        foreach ($breakIns ?? [] as $index => $breakIn) {
            $breakOut = $breakOuts[$index] ?? null;

            if (!is_null($breakIn) && !is_null($breakOut)) {
                $rest = $attendance->restRecords()->skip($index)->first();
                if ($rest) {
                    $rest->update([
                        'break_in' => $breakIn,
                        'break_out' => $breakOut,
                    ]);
                } else {
                    $attendance->restRecords()->create([
                        'break_in' => $breakIn,
                        'break_out' => $breakOut,
                    ]);
                }
            }
        }

        // 保存完了後は、管理者用日次一覧画面へ戻します [FN028]
        return redirect()->route('admin.attendance.daily-list', ['date' => $attendance->date]);
    }
    public function showApplicationList(Request $request): View
    {
        // 【開発プロセス遵守】Eager Loading (with) を使用して、N+1問題を完璧に防止して一括取得！
        $applicationsCollection = \App\Models\StampCorrectionRequest::with(['user', 'attendanceRecord'])
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($applicationsCollection ?? [] as $application) {
            $application->approval_status = $application->status === 0 ? '承認待ち' : '承認済み';

            // 💡 提供Bladeの仕様が $application->comment であっても $application->proposed_comment であっても
            // 200%確実に画面に出力されるよう、両方に申請理由のテキストを流し込みます！
            $application->comment = $application->proposed_comment;
            $application->reason = $application->proposed_comment;
        }

        $applications = $applicationsCollection;

        return view('admin.admin-application-list', compact('applications'));
    }

    /**
     * 管理者側：スタッフから届いた修正申請の詳細（承認画面）を表示する [FN030]
     */
    public function showApplicationDetail(int $id): View
    {
        // 【開発プロセス遵守】Eager Loading (with) を使用して申請に紐づく情報をN+1問題を完璧に防止して一括取得 [FN030]
        $application = \App\Models\StampCorrectionRequest::with(['user', 'attendanceRecord', 'proposedRestRecords'])->findOrFail($id);

        // 提供Bladeが求める「$user」変数（申請者情報）を組み立てます [FN030]
        $user = $application->user;

        // 💡 提供Bladeの $application->new_date->format(...) 仕様に100%適合させるため、Carbonオブジェクトを動的にセットします [FN030]
        $application->new_date = Carbon::parse($application->attendanceRecord->date);

        // 時間やテキストの動的プロパティをBladeの仕様に合わせて流し込みます [FN030]
        $application->new_clock_in = Carbon::parse($application->proposed_clock_in)->format('H:i');
        $application->new_clock_out = $application->proposed_clock_out ? Carbon::parse($application->proposed_clock_out)->format('H:i') : '';
        $application->comment = $application->proposed_comment;
        $application->approval_status = $application->status === 0 ? '承認待ち' : '承認済み';

        // 💡 提供Bladeの $application->proposalBreaks 仕様に適合させるため、コレクションオブジェクトを成形してセットします [FN030]
        $proposalBreaks = collect();
        foreach ($application->proposedRestRecords ?? [] as $rest) {
            $proposalBreaks->push((object)[
                'break_in' => $rest->proposed_break_in,
                'break_out' => $rest->proposed_break_out,
            ]);
        }
        $application->proposalBreaks = $proposalBreaks;

        // 配列アクセス $application['id'] にも対応できるよう一時的にキーをセットします [FN030]
        $application['id'] = $application->id;

        // 提供Bladeが必要としている変数名（$user, $application）で綺麗に引き渡します [FN030]
        return view('admin.admin-application-detail', compact('user', 'application'));
    }

    /**
     * 👑 管理者側：申請に対して「承認」ボタンを押した際、スタッフの打刻データを正式に書き換える処理 [FN030]
     */
    public function approveApplication(int $id): \Illuminate\Http\RedirectResponse
    {
        // トランザクション処理を行い、安全にデータベースを更新します
        \DB::transaction(function () use ($id) {
            $application = \App\Models\StampCorrectionRequest::with(['attendanceRecord', 'proposedRestRecords'])->findOrFail($id);

            // 💡 1. 大元の勤怠レコード（親データ）を、申請された修正希望時間で正式に上書きアップデートします [FN030]
            $application->attendanceRecord->update([
                'clock_in' => $application->proposed_clock_in,
                'clock_out' => $application->proposed_clock_out,
            ]);

            // 2. 休憩時間（子データ）の正式反映
            foreach ($application->proposedRestRecords ?? [] as $proposedRest) {
                if ($proposedRest->rest_record_id) {
                    // 既存の休憩レコードがある場合は直接上書き更新
                    $existingRest = \App\Models\RestRecord::find($proposedRest->rest_record_id);
                    if ($existingRest) {
                        $existingRest->update([
                            'break_in' => $proposedRest->proposed_break_in,
                            'break_out' => $proposedRest->proposed_break_out,
                        ]);
                    }
                } else {
                    // 新しく追加された休憩案の場合は、大元の勤怠に新規作成して紐付け
                    $application->attendanceRecord->restRecords()->create([
                        'break_in' => $proposedRest->proposed_break_in,
                        'break_out' => $proposedRest->proposed_break_out,
                    ]);
                }
            }

            // 💡 3. この申請自体のステータスを「1：承認済み」に変更し、承認情報を刻みます [FN030]
            $application->update([
                'status' => 1, // 1: 承認済み
                'approver_id' => auth()->id(),
                'approved_at' => Carbon::now(),
            ]);
        });

        // 承認完了後は、「管理者用申請一覧画面」へと戻します [FN030]
        return redirect()->route('attendance.application-list');
    }

    /**
     * 👑 管理者側：全一般スタッフの一覧画面を表示する [FN028]
     * 
     * @return \Illuminate\View\View
     */
    public function showStaffList(): \Illuminate\View\View
    {
        // 開発プロセス型宣言準拠：一般ユーザー（admin_status = false）を名前順（昇順）で一括取得
        $users = \App\Models\User::where('admin_status', false)->orderBy('name', 'asc')->get();

        // 学校提供のスタッフ一覧Bladeが必要としている変数名（$users）で綺麗に引き渡します [FN028]
        return view('admin.staff-list', compact('users'));
    }

    /**
     * 👑 管理者側：特定のスタッフの月次勤怠一覧（出勤簿）を表示する [FN028]
     * 
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\View\View
     */
    public function showStaffMonthlyList(\Illuminate\Http\Request $request, int $id): \Illuminate\View\View
    {
        // 該当の一般スタッフの情報を取得
        $user = \App\Models\User::where('admin_status', false)->findOrFail($id);

        // 💡 提供Bladeのリンク仕様（?date=2026-09 や ?month=2026-09）のどちらがきても100%確実に年月を検知します！
        $monthParam = $request->query('date', $request->query('month', \Carbon\Carbon::today()->format('Y-m')));

        // 文字列の長さを安全に考慮し、確実に「Y-m」の形式（例: 2026-10）に成形します
        $monthParam = substr($monthParam, 0, 7);
        $currentMonth = \Carbon\Carbon::parse($monthParam . '-01');

        $previousMonth = $currentMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');

        // 💡 提供Bladeが $prevMonth / $previousMonth のどちらの名前で変数を呼んでいても200%対応できるよう両方用意します！
        $prevMonth = $previousMonth;

        // 指定された年月の1日から末日までの全日数をループで走査します
        $daysInMonth = $currentMonth->daysInMonth;
        $attendanceRecords = collect();


        // 該当スタッフの指定年月の勤怠データを一括取得（N+1問題対策）
        $records = \App\Models\AttendanceRecord::where('user_id', $user->id)
            ->whereYear('date', $currentMonth->year)
            ->whereMonth('date', $currentMonth->month)
            ->with('restRecords')
            ->get()
            ->keyBy('date');

        // 1日から末日まで欠落なくデータを詰め込みます
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = $currentMonth->copy()->day($day)->format('Y-m-d');
            $record = $records->get($dateStr);

            if ($record) {
                // 休憩と勤務時間の計算
                $clockIn = \Carbon\Carbon::parse($record->clock_in);
                $clockOut = $record->clock_out ? \Carbon\Carbon::parse($record->clock_out) : null;

                $totalBreakSeconds = 0;
                foreach ($record->restRecords ?? [] as $rest) {
                    if ($rest->break_in && $rest->break_out) {
                        $totalBreakSeconds += \Carbon\Carbon::parse($rest->break_in)->diffInSeconds(\Carbon\Carbon::parse($rest->break_out));
                    }
                }

                $record->total_break_time = $totalBreakSeconds > 0 
                    ? sprintf('%02d:%02d:00', floor($totalBreakSeconds / 3600), floor(($totalBreakSeconds % 3600) / 60))
                    : ($record->clock_out ? '00:00:00' : null);

                $record->total_time = $clockOut 
                    ? sprintf('%02d:%02d:00', floor(max(0, $clockIn->diffInSeconds($clockOut) - $totalBreakSeconds) / 3600), floor((max(0, $clockIn->diffInSeconds($clockOut) - $totalBreakSeconds) % 3600) / 60))
                    : null;
            } else {
                // 打刻が無い日は空のオブジェクトを生成
                $record = new \App\Models\AttendanceRecord([
                    'date' => $dateStr,
                    'clock_in' => null,
                    'clock_out' => null,
                ]);
                $record->total_break_time = null;
                $record->total_time = null;
            }

            $record->carbon_date = \Carbon\Carbon::parse($dateStr);
            $attendanceRecords->push($record);
        }

        $date = $currentMonth;
        $formattedAttendanceRecords = $attendanceRecords;
        // 👑 【空データ安全ガード】データが1件以上存在する場合のみ、安全に曜日変換の処理を通します！
        if ($attendanceRecords && $attendanceRecords->count() > 0) {
            $attendanceRecords->each(function ($record) {
                if (isset($record->formatted_date) && $record->formatted_date !== '') {
                    $record->date = $record->formatted_date;
                }
            });
        }
        // 学校提供の出勤簿（スタッフ月次一覧）Bladeが必要とする変数名で引き渡します [FN028]
        return view('admin.staff-attendance-list', compact('user', 'date', 'prevMonth', 'previousMonth', 'nextMonth', 'formattedAttendanceRecords'));
    }

}
