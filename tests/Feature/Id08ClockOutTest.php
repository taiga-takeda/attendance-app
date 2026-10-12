<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Id08ClockOutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 08 - 1: 退勤ボタンが正しく機能するかの検証
     */
    public function test_id08_1_clock_out_button_functionality(): void
    {
        $user = User::create([
            'name' => '退勤テストスタッフ',
            'email' => 'clockout-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        // 【前提手順】ステータスが出勤中のユーザーを用意します
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        // 画面に「退勤」ボタン（value="clock_out"）が表示されていることを確認
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('clock_out');

        // 退勤の処理を行う
        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_out',
        ]);
        $response->assertStatus(302);

        // 処理後に画面上に表示されるステータスが「退勤済」になっているか検証
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertSee('退勤済');
    }

    /**
     * ID 08 - 2: 退勤時刻が勤怠一覧画面で確認できるかの検証
     */
    public function test_id08_2_clock_out_time_reflected_on_list(): void
    {
        $user = User::create([
            'name' => '退勤テストスタッフ',
            'email' => 'clockout-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $targetDate = \Carbon\Carbon::today()->format('Y-m-d');

        // 出勤と退勤の処理がなされているレコードを作成
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $targetDate,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 勤怠一覧画面から退勤の日付を確認する
        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);

        // 勤怠一覧画面に退勤時刻が正確に記録・表示されているか検証
        $response->assertSeeInOrder(['18', '00']);
    }
}
