<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\RestRecord;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Http\Requests\StampCorrectionRequest as StampRequest;

class AttendanceRecordController extends Controller
{
    /**
     * 一般ユーザー：打刻画面を表示し、現在のステータスを判定する
     * 
     * @return \Illuminate\View\View
     */
    public function index(): View
    {
        $user = Auth::user();
        $today = Carbon::today()->format('Y-m-d');

        // 1. 提供Bladeの変数形式に合わせて、日付と時刻をフォーマット
        $formattedDate = Carbon::now()->format('Y/m/d') . '(' . Carbon::now()->isoFormat('ddd') . ')';
        $formattedTime = Carbon::now()->format('H:i');

        // 本日の勤怠レコードを取得
        $attendance = AttendanceRecord::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        // 初期ステータスは「勤務外」
        $status = '勤務外';

        if ($attendance) {
            if ($attendance->clock_out) {
                $status = '退勤済';
            } else {
                // 最新の休憩レコードを確認
                $latestRest = RestRecord::where('attendance_record_id', $attendance->id)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($latestRest && is_null($latestRest->break_out)) {
                    $status = '休憩中';
                } else {
                    $status = '出勤中';
                }
            }
        }

        // 提供Bladeの「$user->attendance_status」という記述に対応させるため、
        // ログインユーザーのオブジェクトに一時的に判定したステータスを代入します
        $user->attendance_status = $status;

        return view('user.attendance-register', compact('user', 'formattedDate', 'formattedTime'));
    }

    /**
     * 一般ユーザー：提供Bladeの一体型フォームから送られてくるPOST処理を安全に分岐・保存する
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $action = $request->input('action');
        $user = Auth::user();
        $today = Carbon::today()->format('Y-m-d');
        $nowTime = Carbon::now()->format('H:i:s');

        // 本日の勤怠レコードを取得
        $attendance = AttendanceRecord::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        // ボタンのvalue（action）に応じて処理を分岐
        switch ($action) {
            case 'clock_in': // 出勤ボタン
                if (!$attendance) {
                    AttendanceRecord::create([
                        'user_id' => $user->id,
                        'date' => $today,
                        'clock_in' => $nowTime,
                    ]);
                }
                break;

            case 'break_in': // 休憩入ボタン
                if ($attendance && !$attendance->clock_out) {
                    RestRecord::create([
                        'attendance_record_id' => $attendance->id,
                        'break_in' => $nowTime,
                    ]);
                }
                break;

            case 'break_out': // 休憩戻ボタン
                if ($attendance) {
                    $latestRest = RestRecord::where('attendance_record_id', $attendance->id)
                        ->whereNull('break_out')
                        ->orderBy('id', 'desc')
                        ->first();
                    if ($latestRest) {
                        $latestRest->update([
                            'break_out' => $nowTime,
                        ]);
                    }
                }
                break;

            case 'clock_out': // 退勤ボタン
                if ($attendance && !$attendance->clock_out) {
                    $attendance->update([
                        'clock_out' => $nowTime,
                    ]);
                }
                break;
        }

        return redirect()->back();
    }

    /**
     * 一般ユーザー：ログインユーザーの当月勤怠一覧を表示する
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\View\View
     */
    public function list(Request $request): View
    {
        $user = Auth::user();

        // 1. 提供Bladeの「?date=」パラメータに合わせます
        $monthParam = $request->query('date', Carbon::now()->format('Y-m'));

        // 提供Bladeの $date->format('Y/m') に対応させるためのCarbonオブジェクト
        $date = Carbon::parse($monthParam . '-01');

        // 提供Bladeの $previousMonth / $nextMonth に引き渡す文字列計算
        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        // 【開発プロセス遵守】Eager Loading (with) を使用してN+1問題を絶対に防止します
        $attendanceRecords = AttendanceRecord::where('user_id', $user->id)
            ->where('date', 'like', $monthParam . '%')
            ->orderBy('date', 'asc')
            ->with('restRecords') 
            ->get()
            ->keyBy('date'); // 日付をキーにして検索しやすくします

        // 💡 1日から末日までの全日付のデータを組み立てるためのカレンダー用ループ
        $formattedAttendanceRecords = [];
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        for ($d = $startOfMonth->copy(); $d->lte($endOfMonth); $d->addDay()) {
            $currentDateStr = $d->format('Y-m-d');

            // 💡 曜日を日本語の「(月)〜(日)」形式で取得 [FN023]
            $dayOfWeek = '(' . $d->isoFormat('ddd') . ')';

            // その日の勤怠データがデータベースに実在するかチェック
            if ($attendanceRecords->has($currentDateStr)) {
                $record = $attendanceRecords->get($currentDateStr);
                $clockIn = Carbon::parse($record->clock_in);
                $clockOut = $record->clock_out ? Carbon::parse($record->clock_out) : null;

                // 休憩合計時間の計算を Collection メソッド(filter, sum) でスマートに実装
                $totalBreakSeconds = collect($record->restRecords ?? [])
                    ->filter(function ($rest) {
                        return $rest->break_in && $rest->break_out;
                    })
                    ->sum(function ($rest) {
                        return Carbon::parse($rest->break_in)->diffInSeconds(Carbon::parse($rest->break_out));
                    });

                $totalBreakTime = null;
                $totalTime = null;

                if ($totalBreakSeconds > 0) {
                    $bHours = floor($totalBreakSeconds / 3600);
                    $bMinutes = floor(($totalBreakSeconds % 3600) / 60);
                    $totalBreakTime = sprintf('%02d:%02d:00', $bHours, $bMinutes);
                } elseif ($record->clock_out) {
                    $totalBreakTime = '00:00:00';
                }

                if ($clockOut) {
                    $staySeconds = $clockIn->diffInSeconds($clockOut);
                    $workSeconds = max(0, $staySeconds - $totalBreakSeconds);

                    $wHours = floor($workSeconds / 3600);
                    $wMinutes = floor(($workSeconds % 3600) / 60);
                    $totalTime = sprintf('%02d:%02d:00', $wHours, $wMinutes);
                }

                // データがある日の配列データを流し込みます
                $formattedAttendanceRecords[] = [
                    'id' => $record->id,
                    'date' => $d->format('m/d') . $dayOfWeek, // 例: 09/18(金)
                    'clock_in' => $clockIn->format('H:i'),
                    'clock_out' => $clockOut ? $clockOut->format('H:i') : 'ー',
                    'total_break_time' => $totalBreakTime,
                    'total_time' => $totalTime,
                ];
            } else {
                // 💡 データがない日（休みなど）は空欄（ー）としてカレンダーの行を作成します
                $formattedAttendanceRecords[] = [
                    'id' => null, // 詳細リンクを非表示にするためにnullをセット
                    'date' => $d->format('m/d') . $dayOfWeek, // 例: 09/19(土)
                    'clock_in' => 'ー',
                    'clock_out' => 'ー',
                    'total_break_time' => null,
                    'total_time' => null,
                ];
            }
        }
        // 👑 【空データ安全ガード】データが存在する時のみ安全に曜日変換を掛けます
        if ($attendanceRecords && $attendanceRecords->count() > 0) {
            $attendanceRecords->each(function ($record) {
                if (isset($record->formatted_date) && $record->formatted_date !== '') {
                    $record->date = $record->formatted_date;
                }
            });
        }

        // 提供Bladeが必要としている変数を過不足なくすべてcompactで引き渡します
        return view('user.user-attendance-list', compact(
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }

    /**
     * 一般ユーザー：選択された勤怠レコードの詳細画面を表示する [FN025]
     *
     * @param  int  $id  勤怠レコードID
     * @return \Illuminate\View\View
     */
    public function detail(int $id): View
    {
        $user = Auth::user();

        // 【10-6対応】Eager Loading (with) を使用して休憩と申請履歴をまとめて取得（N+1を絶対に防止）
        $attendance = AttendanceRecord::with(['restRecords', 'stampCorrectionRequests'])->findOrFail($id);

        // ログインしている本人のデータ、または管理者でなければ閲覧できないように制限
        if ($attendance->user_id !== $user->id && !$user->admin_status) {
            abort(403, 'この画面にアクセスする権限がありません。');
        }

        // 表示用に日付のフォーマットを調整
        $carbonDate = Carbon::parse($attendance->date);
        $year = $carbonDate->format('Y');
        $dateStr = $carbonDate->format('m月d日');

        // 休憩レコードのリストを提供Bladeが求める配列構造に変換
        $breaks = [];
        foreach ($attendance->restRecords ?? [] as $rest) {
            $breaks[] = [
                'break_in' => Carbon::parse($rest->break_in)->format('H:i'),
                'break_out' => $rest->break_out ? Carbon::parse($rest->break_out)->format('H:i') : '',
            ];
        }

        // 💡 【10-6徹底補強】DBへの再クエリを完全排除！withで取得済みのコレクションから「firstWhere」を使って高速に抽出します！
        $application = $attendance->stampCorrectionRequests->firstWhere('status', 0);

        // 💡 提供されたBladeの $data の配列キーの仕様と100%一致するようにオブジェクトを組み立てます
        // 💡 承認待ちの申請がある場合は修正希望時間を、無い場合は元の打刻時間を画面の初期値にセットします [FN025]
        $data = [
            'id' => $attendance->id,
            'year' => $year,
            'date' => $dateStr,
            'clock_in' => $application ? Carbon::parse($application->proposed_clock_in)->format('H:i') : Carbon::parse($attendance->clock_in)->format('H:i'),
            'clock_out' => $application
                ? ($application->proposed_clock_out ? Carbon::parse($application->proposed_clock_out)->format('H:i') : '')
                : ($attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : ''),
            'breaks' => $breaks,
            'comment' => $application ? $application->proposed_comment : '',
            'application' => $application,
        ];

        // 提供Bladeが必要としている変数名（$user, $data）で綺麗に引き渡します
        return view('user.user-detail', compact('user', 'data'));
    }

    /**
     * 一般ユーザー：修正申請を保存する処理 [FN026]
     * 
     * @param \App\Http\Requests\StampCorrectionRequest $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(StampRequest $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $attendance = AttendanceRecord::findOrFail($id);

        // 1. 修正申請（親データ）を保存 [FN026]
        $correctionRequest = \App\Models\StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'applicant_id' => $user->id,
            'proposed_clock_in' => $request->input('new_clock_in'),
            'proposed_clock_out' => $request->input('new_clock_out'),
            'proposed_comment' => $request->input('comment'),
            'status' => 0, // 0:承認待ち
        ]);

        // 2. 休憩時間の修正案（子データ）があればループで保存
        $newBreakIns = $request->input('new_break_in', []);
        $newBreakOuts = $request->input('new_break_out', []);

        foreach ($newBreakIns ?? [] as $index => $breakIn) {
            $breakOut = $newBreakOuts[$index] ?? null;

            // 入力欄がどちらも空欄でなければ保存対象とする
            if (!is_null($breakIn) && !is_null($breakOut)) {
                // 既存の休憩レコードのIDを特定（順番通りに紐付け、無ければnull）
                $existingRest = $attendance->restRecords()->skip($index)->first();

                \App\Models\ProposedRestRecord::create([
                    'stamp_correction_request_id' => $correctionRequest->id,
                    'rest_record_id' => $existingRest ? $existingRest->id : null,
                    'proposed_break_in' => $breakIn,
                    'proposed_break_out' => $breakOut,
                ]);
            }
        }

        // 保存が完了したら、マイルストーン6-9の「申請一覧画面」にジャンプさせます
        return redirect()->route('attendance.list');
    }

    /**
     * 一般ユーザー：ログインユーザーの自身の申請一覧を表示する [FN027]
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\View\View
     */
    public function applicationList(Request $request): View
    {
        $user = Auth::user();

        // 【開発プロセス遵守】Eager Loading (with) を使用して、N+1問題を完璧に防止します
        $allRequests = \App\Models\StampCorrectionRequest::where('applicant_id', $user->id)
            ->with('attendanceRecord')
            ->orderBy('created_at', 'desc')
            ->get();

        // 💡 【10-5対応】foreachループを完全排除し、LaravelのCollectionメソッド(map)でスマートに一撃成形します！
        $formattedApplications = $allRequests->map(function ($req) {
            return [
                'id' => $req->id,
                'approval_status' => $req->status === 0 ? '承認待ち' : '承認済み',
                'date' => \Carbon\Carbon::parse($req->attendanceRecord->date)->format('Y/m/d'),
                'comment' => $req->proposed_comment,
                'application_date' => \Carbon\Carbon::parse($req->created_at)->format('Y/m/d'),
            ];
        })->all(); // 既存のBladeが配列としてループ（@foreach）を回せるよう、最後に ->all() で配列に戻して引き渡します

        // 提供Bladeが必要としている変数名（$user, $formattedApplications）で綺麗に引き渡します
        return view('user.user-application-list', compact('user', 'formattedApplications'));
    }
}
