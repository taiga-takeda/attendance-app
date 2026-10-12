<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Id14AdminStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 14 - 1: 管理者ユーザーが全一般ユーザーの「氏名」「メールアドレス」を確認できるかの検証
     */
    public function test_id14_1_admin_can_view_staff_list(): void
    {
        $admin = User::create([
            'name' => 'スタッフ管理管理者',
            'email' => 'admin-staff-list@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        $staff = User::create([
            'name' => '管理対象スタッフA',
            'email' => 'target-staff-a@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);

        // スタッフ一覧ページ（PG12）を開き、名前とメールが正しく表示されているか検証します
        $response = $this->actingAs($admin)->get('/admin/staff/list');
        $response->assertStatus(200);
        $response->assertSee($staff->name);
        $response->assertSee($staff->email);
    }

    /**
     * ID 14 - 2: ユーザーの勤怠情報が正しく表示されるかの検証
     */
    public function test_id14_2_staff_attendance_correctly_displayed(): void
    {
        $admin = User::create([
            'name' => 'スタッフ管理管理者',
            'email' => 'admin-staff-list@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        $staff = User::create([
            'name' => '管理対象スタッフA',
            'email' => 'target-staff-a@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);

        $targetDate = \Carbon\Carbon::today()->format('Y-m-d');
        AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => $targetDate,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 選択したユーザーの勤怠一覧（月次カレンダー：PG11）ページを開きます
        $response = $this->actingAs($admin)->get('/admin/attendance/staff/' . $staff->id);
        $response->assertStatus(200);
        $response->assertSee($staff->name);
        $response->assertSeeInOrder(['09', '00']);
    }

    /**
     * ID 14 - 3: 「前月」を押下した時に表示月の前月の情報が表示される検証
     */
    public function test_id14_3_previous_month_button_functionality(): void
    {
        $admin = User::create([
            'name' => 'スタッフ管理管理者',
            'email' => 'admin-staff-list@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        $staff = User::create([
            'name' => '管理対象スタッフA',
            'email' => 'target-staff-a@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);

        $prevMonthParam = \Carbon\Carbon::today()->subMonth()->format('Y-m');
        $prevMonthDisplay = \Carbon\Carbon::today()->subMonth()->format('Y/m');

        // 前月のクエリを送り、管理者向け出勤簿の表示年月が切り替わるか検証
        $response = $this->actingAs($admin)->get('/admin/attendance/staff/' . $staff->id . '?date=' . $prevMonthParam);
        $response->assertStatus(200);
        $response->assertSee($prevMonthDisplay);
    }

    /**
     * ID 14 - 4: 「翌月」を押下した時に表示月の翌月の情報が表示される検証
     */
    public function test_id14_4_next_month_button_functionality(): void
    {
        $admin = User::create([
            'name' => 'スタッフ管理管理者',
            'email' => 'admin-staff-list@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        $staff = User::create([
            'name' => '管理対象スタッフA',
            'email' => 'target-staff-a@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);

        $nextMonthParam = \Carbon\Carbon::today()->addMonth()->format('Y-m');
        $nextMonthDisplay = \Carbon\Carbon::today()->addMonth()->format('Y/m');

        // 翌月のクエリを送り、管理者向け出勤簿の表示年月が切り替わるか検証
        $response = $this->actingAs($admin)->get('/admin/attendance/staff/' . $staff->id . '?date=' . $nextMonthParam);
        $response->assertStatus(200);
        $response->assertSee($nextMonthDisplay);
    }

    /**
     * ID 14 - 5: 「詳細」を押下すると、その日の勤怠詳細画面に遷移する検証
     */
    public function test_id14_5_navigate_to_staff_detail_page(): void
    {
        $admin = User::create([
            'name' => 'スタッフ管理管理者',
            'email' => 'admin-staff-list@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        $staff = User::create([
            'name' => '管理対象スタッフA',
            'email' => 'target-staff-a@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);

        $targetDate = \Carbon\Carbon::today()->format('Y-m-d');
        $attendance = AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => $targetDate,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 管理者側から見たスタッフ別勤怠詳細（PG08）へ正常にジャンプできるか検証
        $response = $this->actingAs($admin)->get('/admin/attendance/' . $attendance->id);
        $response->assertStatus(200);
    }
}
