<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\RestRecord;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AttendanceAndRestSeeder extends Seeder
{
    public function run(): void
    {
        $user1 = User::where('email', 'user1@example.com')->first();
        if (!$user1) return;

        $now = Carbon::now();

        // 1. 過去5ヶ月分のデータ作成（各月平日15日ずつの通常勤務 9:00-18:00）
        // 5ヶ月 × 15日 = 75日間（労働時間：8時間 × 75 = 600時間）
        for ($i = 5; $i >= 1; $i--) {
            $targetMonth = $now->copy()->subMonths($i);
            $processedDays = 0;

            for ($day = 1; $day <= 31; $day++) {
                if ($processedDays >= 15) break;

                $date = $targetMonth->copy()->day($day);
                if ($date->isWeekend()) continue; // 平日のみ対象

                $attendance = AttendanceRecord::create([
                    'user_id' => $user1->id,
                    'date' => $date->format('Y-m-d'),
                    'clock_in' => '09:00:00',
                    'clock_out' => '18:00:00',
                ]);

                RestRecord::create([
                    'attendance_record_id' => $attendance->id,
                    'break_in' => '12:00:00',
                    'break_out' => '13:00:00',
                ]);

                $processedDays++;
            }
        }

        // 2. 当月（今月）の17日間のデータ作成（特殊パターン）
        // 労働時間の合計：通常80時間 + 残業30時間 + 遅刻15時間 + 早退7時間 + 長時間12時間 = 144時間
        // 過去6ヶ月総労働時間：600時間 + 144時間 = 744時間
        // 過去6ヶ月総残業時間：残業3日間(各2時間超過) + 長時間1日(4時間超過) = 10時間
        $patterns = [
            ['type' => '通常', 'count' => 10, 'in' => '09:00:00', 'out' => '18:00:00'], // 8時間労働 × 10 = 80時間
            ['type' => '残業', 'count' => 3,  'in' => '09:00:00', 'out' => '20:00:00'], // 10時間労働(残業2) × 3 = 30時間
            ['type' => '遅刻', 'count' => 2,  'in' => '09:30:00', 'out' => '18:00:00'], // 7.5時間労働 × 2 = 15時間
            ['type' => '早退', 'count' => 1,  'in' => '09:00:00', 'out' => '17:00:00'], // 7時間労働 × 1 = 7時間
            ['type' => '長時間', 'count' => 1, 'in' => '08:00:00', 'out' => '21:00:00'], // 12時間労働(残業4) × 1 = 12時間
        ];

        $currentMonth = $now->copy();
        $dayCounter = 1;

        foreach ($patterns ?? [] as $pattern) {
            for ($c = 0; $c < $pattern['count']; $c++) {
                while (true) {
                    $date = $currentMonth->copy()->day($dayCounter);
                    $dayCounter++;
                    if (!$date->isWeekend()) break; // 平日のみに配置
                }

                $attendance = AttendanceRecord::create([
                    'user_id' => $user1->id,
                    'date' => $date->format('Y-m-d'),
                    'clock_in' => $pattern['in'],
                    'clock_out' => $pattern['out'],
                ]);

                // user1の全レコードに固定休憩 12:00-13:00（1時間）を付与
                RestRecord::create([
                    'attendance_record_id' => $attendance->id,
                    'break_in' => '12:00:00',
                    'break_out' => '13:00:00',
                ]);
            }
        }
    }
}
