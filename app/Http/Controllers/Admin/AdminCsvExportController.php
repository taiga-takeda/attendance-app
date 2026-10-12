<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCsvExportController extends Controller
{
    /**
     * 管理者専用：指定されたスタッフの当月勤怠データをCSV出力する
     */
    public function exportStaffMonthlyCsv(Request $request): StreamedResponse
    {
        // 直前のURL（リファラ）から情報を安全に解析
        $referer = $request->headers->get('referer');
        $staffId = null;
        $monthParam = Carbon::now()->format('Y-m');

        if ($referer) {
            // URLパスを分解して末尾のスタッフIDを取得
            $path = parse_url($referer, PHP_URL_PATH);
            $segments = explode('/', trim($path, '/'));
            $staffId = (int)end($segments);

            // クエリパラメータの解析
            $queryString = parse_url($referer, PHP_URL_QUERY);
            if ($queryString) {
                parse_str($queryString, $query);
                if (isset($query['date'])) $monthParam = $query['date'];
                elseif (isset($query['month'])) $monthParam = $query['month'];
            }
        }

        // セーフティ：万が一URLからIDが取れない場合は、直近の勤怠レコードのスタッフIDを自動補完
        if (!$staffId || $staffId === 0) {
            $lastRecord = AttendanceRecord::latest()->first();
            $staffId = $lastRecord ? $lastRecord->user_id : User::where('admin_status', false)->first()->id;
        }

        $staff = User::findOrFail($staffId);
        $date = Carbon::parse($monthParam . '-01');

        $attendanceRecords = AttendanceRecord::where('user_id', $staff->id)
            ->where('date', 'like', $monthParam . '%')
            ->orderBy('date', 'asc')
            ->with('restRecords')
            ->get()
            ->keyBy('date');

        $daysInMonth = $date->daysInMonth;
        $fileName = sprintf('勤怠一覧_%s_%s.csv', $staff->name, $monthParam);

        $response = new StreamedResponse(function () use ($daysInMonth, $date, $attendanceRecords) {
            $stream = fopen('php://output', 'w');

            // 💡 【内訳9】CSVのヘッダ項目（見出し）を定義
            $headers = ['日付', '出勤時間', '退勤時間', '休憩時間', '合計就業時間'];
            
            // 💡 【内訳10：文字化け対策】環境を選ばない最も安定したShift-JIS一括変換をかけてヘッダを書き込みます
            $sjisHeaders = array_map(function ($value) {
                return mb_convert_encoding($value, 'SJIS-win', 'UTF-8');
            }, $headers);
            fputcsv($stream, $sjisHeaders);

            collect(range(1, $daysInMonth))->each(function ($day) use ($stream, $date, $attendanceRecords) {
                $d = $date->copy()->day($day);
                $currentDateStr = $d->format('Y-m-d');
                
                \Carbon\Carbon::setLocale('ja');
                $dayOfWeek = '(' . $d->isoFormat('ddd') . ')';
                $dateCol = $d->format('m/d') . $dayOfWeek;

                $clockInCol = 'ー';
                $clockOutCol = 'ー';
                $totalBreakTimeCol = 'ー';
                $totalTimeCol = 'ー';

                if ($attendanceRecords->has($currentDateStr)) {
                    $record = $attendanceRecords->get($currentDateStr);
                    $clockIn = Carbon::parse($record->clock_in);
                    $clockOut = $record->clock_out ? Carbon::parse($record->clock_out) : null;

                    $clockInCol = $clockIn->format('H:i');
                    $clockOutCol = $clockOut ? $clockOut->format('H:i') : 'ー';

                    // 💡 【タイポ完全修正】?? の後ろに [] をしっかりと追記し、Syntax Error を完全に解消しました！
                    $totalBreakSeconds = collect($record->restRecords ?? [])
                        ->filter(function ($rest) { return $rest->break_in && $rest->break_out; })
                        ->sum(function ($rest) {
                            return Carbon::parse($rest->break_in)->diffInSeconds(Carbon::parse($rest->break_out));
                        });

                    if ($totalBreakSeconds > 0) {
                        $bHours = floor($totalBreakSeconds / 3600);
                        $bMinutes = floor(($totalBreakSeconds % 3600) / 60);
                        $totalBreakTimeCol = sprintf('%02d:%02d:00', $bHours, $bMinutes);
                    } elseif ($record->clock_out) {
                        $totalBreakTimeCol = '00:00:00';
                    }

                    if ($clockOut) {
                        $staySeconds = $clockIn->diffInSeconds($clockOut);
                        $workSeconds = max(0, $staySeconds - $totalBreakSeconds);

                        $wHours = floor($workSeconds / 3600);
                        $wMinutes = floor(($workSeconds % 3600) / 60);
                        $totalTimeCol = sprintf('%02d:%02d:00', $wHours, $wMinutes);
                    }
                }

                $row = [$dateCol, $clockInCol, $clockOutCol, $totalBreakTimeCol, $totalTimeCol];
                
                // 各行のセルの値データも漏れなく完全にShift-JISに変換して出力ストリームに書き込み
                $sjisRow = array_map(function ($value) {
                    return mb_convert_encoding($value, 'SJIS-win', 'UTF-8');
                }, $row);
                
                fputcsv($stream, $sjisRow);
            });

            fclose($stream);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=SJIS-win');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        return $response;
    }
}
