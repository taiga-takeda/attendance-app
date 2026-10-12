<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Id09UserAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 09 - 1: 自分が判定した勤怠情報が全て表示されているかの検証
     */
    public function test_id09_1_all_own_attendance_records_displayed(): void
    {
        $user = User::create([
            'name' => '一覧テストスタッフ',
            'email' => 'list-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $targetDate = \Carbon\Carbon::today()->startOfMonth()->format('Y-m-d');
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $targetDate,
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSeeInOrder(['10', '00']);
    }

    /**
     * ID 09 - 2: 勤怠一覧画面に遷移した際に現在の月が表示される検証
     */
    public function test_id09_2_current_month_displayed_by_default(): void
    {
        $user = User::create([
            'name' => '一覧テストスタッフ',
            'email' => 'list-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $thisMonthStr = \Carbon\Carbon::today()->format('Y/m');

        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee($thisMonthStr);
    }

    /**
     * ID 09 - 3: 「前月」を押下した時に表示月の前月の情報が表示される検証
     */
    public function test_id09_3_previous_month_button_functionality(): void
    {
        $user = User::create([
            'name' => '一覧テストスタッフ',
            'email' => 'list-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        // 前月の文字列（例: 2026-09）をクエリに送り、画面上に「2026/09」が切り替わって表示されているか検証
        $prevMonthParam = \Carbon\Carbon::today()->subMonth()->format('Y-m');
        $prevMonthDisplay = \Carbon\Carbon::today()->subMonth()->format('Y/m');

        $response = $this->actingAs($user)->get('/attendance/list?date=' . $prevMonthParam);
        $response->assertStatus(200);
        $response->assertSee($prevMonthDisplay);
    }

    /**
     * ID 09 - 4: 「翌月」を押下した時に表示月の翌月の情報が表示される検証
     */
    public function test_id09_4_next_month_button_functionality(): void
    {
        $user = User::create([
            'name' => '一覧テストスタッフ',
            'email' => 'list-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        // 翌月の文字列（例: 2026-11）をクエリに送り、画面上に「2026/11」が切り替わって表示されているか検証
        $nextMonthParam = \Carbon\Carbon::today()->addMonth()->format('Y-m');
        $nextMonthDisplay = \Carbon\Carbon::today()->addMonth()->format('Y/m');

        $response = $this->actingAs($user)->get('/attendance/list?date=' . $nextMonthParam);
        $response->assertStatus(200);
        $response->assertSee($nextMonthDisplay);
    }

    /**
     * ID 09 - 5: 「詳細」を押下すると、その日の勤怠詳細画面に遷移する検証
     */
    public function test_id09_5_navigate_to_detail_page(): void
    {
        $user = User::create([
            'name' => '一覧テストスタッフ',
            'email' => 'list-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $targetDate = \Carbon\Carbon::today()->startOfMonth()->format('Y-m-d');
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $targetDate,
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/' . $attendance->id);
        $response->assertStatus(200);
        $response->assertSee('勤怠詳細');
    }
}
