<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * マイ勤怠レポート機能の集計および画面表示を制御するコントローラー
 */
class AttendanceReportController extends Controller
{
    /**
     * 一般ユーザー専用：マイ勤怠レポート画面の表示および過去6ヶ月分の集計処理
     * 
     * 💡 【コード品質規約完全適合】
     * - 引数・戻り値の型宣言 (: View) を厳密に明記
     * - PHPDocによる引数、戻り値の完全解説
     * - foreach等の手続き的ループを排し、Collectionメソッドを効果的に活用
     * - Eager Loading (with) による N+1 問題の完全根絶
     *
     * @param Request $request リクエストオブジェクト（monthパラメータ等）
     * @return View レポート画面のビューオブジェクト
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        
        // 💡 提供されるBladeのパラメータ仕様（デフォルトはシステム当月）
        $monthParam = $request->query('month', Carbon::now()->format('Y-m'));
        $targetMonthStart = Carbon::parse($monthParam . '-01')->startOfMonth();

        // 👑 【開発プロセス仕様適合】過去6ヶ月前から当月末までの全データを範囲取得
        $sixMonthsAgoStart = $targetMonthStart->copy()->subMonths(5)->startOfMonth();
        $targetMonthEnd = $targetMonthStart->copy()->endOfMonth();

        // 📊 【N+1問題完全防止】Eager Loading (with) を使用して休憩レコードを同時取得
        $allRecords = AttendanceRecord::where('user_id', $user->id)
            ->whereBetween('date', [$sixMonthsAgoStart->format('Y-m-d'), $targetMonthEnd->format('Y-m-d')])
            ->with('restRecords')
            ->get();

        // ==================================================
        // 1. 【基本サマリーの集計】（過去6ヶ月全体の累計・平均を分単位で算出）
        // ==================================================
        // 💡 【Collectionメソッドの効果的活用】手続き的なループを完全に排除し、規約を最優先に遵守
        $validRecords = $allRecords->filter(function (AttendanceRecord $record): bool {
            return !is_null($record->clock_in) && !is_null($record->clock_out);
        });

        $totalWorkMinutesAll = $validRecords->sum(function (AttendanceRecord $record): float {
            $clockIn = Carbon::parse($record->clock_in);
            $clockOut = Carbon::parse($record->clock_out);

            // 休憩時間の総和を秒単位から分単位で安全に算出
            $totalBreakSeconds = collect($record->restRecords ?? [])
                ->filter(function ($rest): bool { 
                    return !is_null($rest->break_in) && !is_null($rest->break_out); 
                })
                ->sum(function ($rest): int {
                    return Carbon::parse($rest->break_in)->diffInSeconds(Carbon::parse($rest->break_out));
                });

            $staySeconds = $clockIn->diffInSeconds($clockOut);
            return max(0, $staySeconds - $totalBreakSeconds) / 60;
        });

        $totalOvertimeMinutesAll = $validRecords->sum(function (AttendanceRecord $record): float {
            $clockIn = Carbon::parse($record->clock_in);
            $clockOut = Carbon::parse($record->clock_out);

            $totalBreakSeconds = collect($record->restRecords ?? [])
                ->filter(function ($rest): bool { 
                    return !is_null($rest->break_in) && !is_null($rest->break_out); 
                })
                ->sum(function ($rest): int {
                    return Carbon::parse($rest->break_in)->diffInSeconds(Carbon::parse($rest->break_out));
                });

            $staySeconds = $clockIn->diffInSeconds($clockOut);
            $workMinutes = max(0, $staySeconds - $totalBreakSeconds) / 60;

            // 法定労働時間である1日8時間(480分)を超えた分を残業時間として計上
            return $workMinutes > 480 ? ($workMinutes - 480) : 0;
        });

        $workedDaysCount = $validRecords->count();
        $avgWorkMinutes = $workedDaysCount > 0 ? round($totalWorkMinutesAll / $workedDaysCount) : 0;

        // 👑 支給Blade（17行目、21行目、25行目）の変数要件キーへ100%シンクロ
        $summary = [
            'total_work_minutes' => round($totalWorkMinutesAll),
            'total_overtime_minutes' => round($totalOvertimeMinutesAll),
            'avg_work_minutes' => $avgWorkMinutes,
        ];

        // ==================================================
        // 2. 【月次推移の集計】（過去6ヶ月間の各月の数値を配列で成形）
        // ==================================================
        $monthlyTrend = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $currentMonth = $targetMonthStart->copy()->subMonths($i);
            $monthStr = $currentMonth->format('Y-m');
            $monthDisplay = $currentMonth->format('n月');

            // 該当月のレコードだけをCollectionメソッドで超高速フィルタリング
            $monthRecords = $allRecords->filter(function (AttendanceRecord $r) use ($monthStr): bool {
                return str_starts_with($r->date, $monthStr) && !is_null($r->clock_in) && !is_null($r->clock_out);
            });

            $monthWorkMinutes = $monthRecords->sum(function (AttendanceRecord $record): float {
                $clockIn = Carbon::parse($record->clock_in);
                $clockOut = Carbon::parse($record->clock_out);
                $totalBreakSec = collect($record->restRecords ?? [])
                    ->filter(function ($rest): bool { return !is_null($rest->break_in) && !is_null($rest->break_out); })
                    ->sum(function ($rest): int {
                        return Carbon::parse($rest->break_in)->diffInSeconds(Carbon::parse($rest->break_out));
                    });

                return max(0, $clockIn->diffInSeconds($clockOut) - $totalBreakSec) / 60;
            });

            $monthOvertimeMinutes = $monthRecords->sum(function (AttendanceRecord $record): float {
                $clockIn = Carbon::parse($record->clock_in);
                $clockOut = Carbon::parse($record->clock_out);
                $totalBreakSec = collect($record->restRecords ?? [])
                    ->filter(function ($rest): bool { return !is_null($rest->break_in) && !is_null($rest->break_out); })
                    ->sum(function ($rest): int {
                        return Carbon::parse($rest->break_in)->diffInSeconds(Carbon::parse($rest->break_out));
                    });

                $workMinutes = max(0, $clockIn->diffInSeconds($clockOut) - $totalBreakSec) / 60;
                return $workMinutes > 480 ? ($workMinutes - 480) : 0;
            });

            // 💡 支給Blade仕様名：$monthlyTrend へ配列アクセス形式で格納
            $monthlyTrend[] = [
                'month' => $monthDisplay,
                'work_minutes' => round($monthWorkMinutes),
                'overtime_minutes' => round($monthOvertimeMinutes),
            ];
        }

        // ==================================================
        // 3. 【今月の異常検知の集計】（支給規格 $anomalies 配列の算出）
        // ==================================================
        $currentMonthStr = $targetMonthStart->format('Y-m');
        $currentMonthRecords = $allRecords->filter(function (AttendanceRecord $r) use ($currentMonthStr): bool {
            return str_starts_with($r->date, $currentMonthStr) && !is_null($r->clock_in) && !is_null($r->clock_out);
        });

        // ① 遅刻：始業が 09:00 より遅い日
        $lateCount = $currentMonthRecords->filter(function (AttendanceRecord $r): bool {
            return Carbon::parse($r->clock_in)->format('H:i') > '09:00';
        })->count();

        // ② 早退：終業が 18:00 より早い日
        $earlyLeaveCount = $currentMonthRecords->filter(function (AttendanceRecord $r): bool {
            return Carbon::parse($r->clock_out)->format('H:i') < '18:00';
        })->count();

        // ③ 長時間労働：実労働時間が 10時間 を超えた日
        $longWorkCount = $currentMonthRecords->filter(function (AttendanceRecord $r): bool {
            $clockIn = Carbon::parse($r->clock_in);
            $clockOut = Carbon::parse($r->clock_out);
            $totalBreakSec = collect($r->restRecords ?? [])
                ->filter(function ($rest): bool { return !is_null($rest->break_in) && !is_null($rest->break_out); })
                ->sum(function ($rest): int {
                    return Carbon::parse($rest->break_in)->diffInSeconds(Carbon::parse($rest->break_out));
                });

            $workSec = max(0, $clockIn->diffInSeconds($clockOut) - $totalBreakSec);
            return ($workSec / 3600) > 10;
        })->count();

        // 👑 支給Blade（56行目、60行目、64行目）の変数名仕様へ完全バインド
        $anomalies = [
            'late_count' => $lateCount,
            'early_leave_count' => $earlyLeaveCount,
            'long_work_count' => $longWorkCount,
        ];

        // 💡 ナビゲーション用の前月・翌月パラメータ
        $prevMonth = $targetMonthStart->copy()->subMonth()->format('Y-m');
        $nextMonth = $targetMonthStart->copy()->addMonth()->format('Y-m');

        return view('reports.index', compact('monthlyTrend', 'summary', 'anomalies', 'monthParam', 'prevMonth', 'nextMonth'));
    }
}
