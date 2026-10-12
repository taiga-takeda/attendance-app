<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Id06ClockInTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 06 - 1: 出勤ボタンが正しく機能し、ステータス連動およびDB保存されるかの検証
     */
    public function test_id06_1_clock_in_button_functionality(): void
    {
        $user = User::create([
            'name' => '出勤テストスタッフ',
            'email' => 'clockin-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $todayStr = \Carbon\Carbon::today()->format('Y-m-d');

        // 勤務外の状態で打刻画面を開き、「出勤」ボタンが存在することを確認してPOST送信（出勤打刻）
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('clock_in');

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ]);
        $response->assertStatus(302);

        // 処理後にステータスが「出勤中」に変更されているか検証
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertSee('出勤中');

        // データベースに本日の出勤データが正しく保存されているか検証
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => $todayStr,
        ]);
    }

    /**
     * ID 06 - 2: 出勤は一日一回のみできるかの検証（退勤済みの場合は出勤ボタン非表示）
     */
    public function test_id06_2_clock_in_only_once_a_day(): void
    {
        $user = User::create([
            'name' => '出勤テストスタッフ',
            'email' => 'clockin-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $todayStr = \Carbon\Carbon::today()->format('Y-m-d');

        // 退勤済みの状態（出勤後に退勤打刻を行った状態）をシミュレート
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $todayStr,
            'clock_in' => '09:00:00',
            'clock_out' => \Carbon\Carbon::now()->format('H:i:s'),
        ]);

        // 退勤済みの状態で打刻画面を開いた際、画面上に「出勤」ボタンが表示されないことを検証
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertDontSee('button class="attendance__button--submit--clock-in"');
    }

    /**
     * ID 06 - 3: 出勤時刻が勤怠一覧画面（カレンダー出勤簿）で正確に確認できるかの検証
     */
    public function test_id06_3_clock_in_time_reflected_on_list(): void
    {
        $user = User::create([
            'name' => '出勤テストスタッフ',
            'email' => 'clockin-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $todayStr = \Carbon\Carbon::today()->format('Y-m-d');

        // 出勤時刻を「09:00:00」としてテスト用レコードをデータベースに直接作成
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $todayStr,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00', // 一覧に表示させるため退勤もセット
        ]);

        // 勤怠一覧画面を開く
        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);

        // 一覧画面の中に、自分の出勤時間「09:00」が正確に記録・出力されているか検証
        $response->assertSee('09:00');
    }
}
