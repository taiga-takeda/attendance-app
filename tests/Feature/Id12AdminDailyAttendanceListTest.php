<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Id12AdminDailyAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 12 - 1: その日になされた全ユーザーの勤怠情報が正確に確認できるかの検証
     */
    public function test_id12_1_all_users_attendance_correctly_displayed(): void
    {
        // テスト用の管理者ユーザーを用意
        $admin = User::create([
            'name' => '一覧確認管理者',
            'email' => 'admin-list@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        // 打刻データを持つスタッフを用意
        $staff = User::create([
            'name' => '打刻スタッフA',
            'email' => 'staff-a@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);

        $todayStr = \Carbon\Carbon::today()->format('Y-m-d');
        AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => $todayStr,
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/list');
        $response->assertStatus(200);

        // 管理者がその日のスタッフ名と出勤打刻を正確に目視確認できるか検証
        $response->assertSee($staff->name);
        $response->assertSeeInOrder(['08', '30']);
    }

    /**
     * ID 12 - 2: 勤怠一覧画面に遷移した際に現在の日付が表示される検証
     */
    public function test_id12_2_current_date_displayed_by_default(): void
    {
        $admin = User::create([
            'name' => '一覧確認管理者',
            'email' => 'admin-list@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        // 💡 画面上の表示仕様に合わせてスラッシュ区切り（Y/m/d）で検証します
        $todayDisplay = \Carbon\Carbon::today()->format('Y/m/d');

        $response = $this->actingAs($admin)->get('/admin/attendance/list');
        $response->assertStatus(200);
        $response->assertSee($todayDisplay);
    }

    /**
     * ID 12 - 3: 「前日」を押下した時に前の日の勤怠情報が表示される検証
     */
    public function test_id12_3_previous_day_button_functionality(): void
    {
        $admin = User::create([
            'name' => '一覧確認管理者',
            'email' => 'admin-list@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        // URLパラメータはハイフン、画面表示はスラッシュ（Y/m/d）に完全に適合させます！
        $prevDayParam = \Carbon\Carbon::today()->subDay()->format('Y-m-d');
        $prevDayDisplay = \Carbon\Carbon::today()->subDay()->format('Y/m/d');

        $response = $this->actingAs($admin)->get('/admin/attendance/list?date=' . $prevDayParam);
        $response->assertStatus(200);
        $response->assertSee($prevDayDisplay);
    }

    /**
     * ID 12 - 4: 「翌日」を押下した時に次の日の勤怠情報が表示される検証
     */
    public function test_id12_4_next_day_button_functionality(): void
    {
        $admin = User::create([
            'name' => '一覧確認管理者',
            'email' => 'admin-list@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        // URLパラメータはハイフン、画面表示はスラッシュ（Y/m/d）に完全に適合させます！
        $nextDayParam = \Carbon\Carbon::today()->addDay()->format('Y-m-d');
        $nextDayDisplay = \Carbon\Carbon::today()->addDay()->format('Y/m/d');

        $response = $this->actingAs($admin)->get('/admin/attendance/list?date=' . $nextDayParam);
        $response->assertStatus(200);
        $response->assertSee($nextDayDisplay);
    }
}
