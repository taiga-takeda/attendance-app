<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * システム現在月と自動連動して平日のみにデータを配置するSeeder
 */
class AttendanceReportVerificationSeeder extends Seeder
{
    /**
     * user1に対してシステム現在月を基準とした平日限定の打刻パターンを動的生成する
     *
     * @return void
     */
    public function run(): void
    {
        $user1 = User::where('email', 'user1@example.com')->first();
        if (!$user1) {
            $user1 = User::create([
                'name' => 'ユーザー1',
                'email' => 'user1@example.com',
                'password' => bcrypt('password'),
                'admin_status' => false,
                'email_verified_at' => now(),
            ]);
        }

        // 既存のデータを完全にクレンジングして重複を防止
        AttendanceRecord::where('user_id', $user1->id)->delete();

        // 【現在月完全自動連動】実行した「その月」の1日をシステムが自動検知して動的基準日に設定
        $targetMonth = Carbon::now()->startOfMonth();

        // --------------------------------------------------
        // 【仕様①】過去5ヶ月：各月平日15日 ＝ 合計75日の通常勤務 (9:00-18:00)
        // --------------------------------------------------
        for ($i = 5; $i >= 1; $i--) {
            $monthDate = $targetMonth->copy()->subMonths($i);
            $days = $monthDate->daysInMonth;
            $count = 0;

            for ($day = 1; $day <= $days; $day++) {
                $current = $monthDate->copy()->day($day);
                if ($current->isWeekday()) {
                    $this->createRecord($user1->id, $current->format('Y-m-d'), '09:00:00', '18:00:00');
                    $count++;
                    if ($count === 15) break;
                }
            }
        }

        // --------------------------------------------------
        //【仕様②】当月17日の特定パターンを「平日のみ」に限定して分散生成
        // --------------------------------------------------
        $currentDay = $targetMonth->copy()->startOfMonth();

        // 1. パターンA: 通常勤務 (9:00-18:00) ➔ 10日間
        for ($i = 0; $i < 10; $i++) {
            while (!$currentDay->isWeekday()) { $currentDay->addDay(); }
            $this->createRecord($user1->id, $currentDay->format('Y-m-d'), '09:00:00', '18:00:00');
            $currentDay->addDay();
        }

        // 2. パターンB: 残業 (9:00-20:00) ➔ 3日間
        for ($i = 0; $i < 3; $i++) {
            while (!$currentDay->isWeekday()) { $currentDay->addDay(); }
            $this->createRecord($user1->id, $currentDay->format('Y-m-d'), '09:00:00', '20:00:00');
            $currentDay->addDay();
        }

        // 3. パターンC: 遅刻 (9:30-18:00) ➔ 2日間
        for ($i = 0; $i < 2; $i++) {
            while (!$currentDay->isWeekday()) { $currentDay->addDay(); }
            $this->createRecord($user1->id, $currentDay->format('Y-m-d'), '09:30:00', '18:00:00');
            $currentDay->addDay();
        }

        // 4. パターンD: 早退 (9:00-17:00) ➔ 1日間
        for ($i = 0; $i < 1; $i++) {
            while (!$currentDay->isWeekday()) { $currentDay->addDay(); }
            $this->createRecord($user1->id, $currentDay->format('Y-m-d'), '09:00:00', '17:00:00');
            $currentDay->addDay();
        }

        // 5. パターンE: 長時間労働 (8:00-21:00) ➔ 1日間
        for ($i = 0; $i < 1; $i++) {
            while (!$currentDay->isWeekday()) { $currentDay->addDay(); }
            $this->createRecord($user1->id, $currentDay->format('Y-m-d'), '08:00:00', '21:00:00');
            $currentDay->addDay();
        }
    }

    /**
     * 補助メソッド：固定休憩 12:00-13:00 (1時間) を付与
     */
    private function createRecord(int $userId, string $date, string $in, string $out): void
    {
        $attendance = AttendanceRecord::create([
            'user_id' => $userId,
            'date' => $date,
            'clock_in' => $in,
            'clock_out' => $out,
        ]);

        $attendance->restRecords()->create([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);
    }
}
