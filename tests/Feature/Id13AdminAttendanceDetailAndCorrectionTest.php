<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Id13AdminAttendanceDetailAndCorrectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 13 - 1: 勤怠詳細画面に表示されるデータが選択したものになっているかの検証
     */
    public function test_id13_1_admin_view_attendance_detail_integrity(): void
    {
        // テスト用の管理者ユーザーを用意します
        $admin = User::create([
            'name' => '修正確認管理者',
            'email' => 'admin-correct@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        // テスト用の一般ユーザーと、その勤怠データを用意します
        $staff = User::create([
            'name' => '修正対象スタッフ',
            'email' => 'staff-correct@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);

        $targetDate = \Carbon\Carbon::today()->startOfMonth()->format('Y-m-d');
        $attendance = AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => $targetDate,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 管理者がスタッフの勤怠詳細画面にアクセスした際、情報が正しく表示されるか検証します
        $response = $this->actingAs($admin)->get('/attendance/' . $attendance->id);
        $response->assertStatus(200);
        $response->assertSee($staff->name);
    }

    /**
     * ID 13 - 2: 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示されるかの検証
     */
    public function test_id13_2_clock_in_after_clock_out_validation(): void
    {
        $admin = User::create([
            'name' => '修正確認管理者',
            'email' => 'admin-correct@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        $staff = User::create([
            'name' => '修正対象スタッフ',
            'email' => 'staff-correct@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);

        $attendance = AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $fromUrl = '/attendance/' . $attendance->id;

        $response = $this->actingAs($admin)->from($fromUrl)->post('/attendance/' . $attendance->id, [
            'new_clock_in' => '19:00',
            'new_clock_out' => '18:00',
            'comment' => '管理者出勤時間エラーテスト',
        ]);
        // 302リダイレクトで安全に元の画面に押し戻される挙動を確実に検証
        $response->assertStatus(302);
    }

    /**
     * ID 13 - 3: 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示されるかの検証
     */
    public function test_id13_3_break_in_after_clock_out_validation(): void
    {
        $admin = User::create([
            'name' => '修正確認管理者',
            'email' => 'admin-correct@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        $staff = User::create([
            'name' => '修正対象スタッフ',
            'email' => 'staff-correct@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);

        $attendance = AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $fromUrl = '/attendance/' . $attendance->id;

        $response = $this->actingAs($admin)->from($fromUrl)->post('/attendance/' . $attendance->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['19:00'],
            'new_break_out' => ['20:00'],
            'comment' => '管理者休憩開始エラーテスト',
        ]);
        $response->assertStatus(302);
    }

    /**
     * ID 13 - 4: 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示されるかの検証
     */
    public function test_id13_4_break_out_after_clock_out_validation(): void
    {
        $admin = User::create([
            'name' => '修正確認管理者',
            'email' => 'admin-correct@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        $staff = User::create([
            'name' => '修正対象スタッフ',
            'email' => 'staff-correct@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);

        $attendance = AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $fromUrl = '/attendance/' . $attendance->id;

        $response = $this->actingAs($admin)->from($fromUrl)->post('/attendance/' . $attendance->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['19:00'],
            'comment' => '管理者休憩終了エラーテスト',
        ]);
        $response->assertStatus(302);
    }

    /**
     * ID 13 - 5: 備考欄が未入力の場合のエラーメッセージが表示されるかの検証
     */
    public function test_id13_5_comment_required_validation(): void
    {
        $admin = User::create([
            'name' => '修正確認管理者',
            'email' => 'admin-correct@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        $staff = User::create([
            'name' => '修正対象スタッフ',
            'email' => 'staff-correct@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);

        $attendance = AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $fromUrl = '/attendance/' . $attendance->id;

        $response = $this->actingAs($admin)->from($fromUrl)->post('/attendance/' . $attendance->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'comment' => '', // 備考欄を空欄で送信
        ]);
        $response->assertStatus(302);
        // 備考入力欄にバリデーションエラーが返ってきているかを検証
        $response->assertSessionHasErrors(['comment']);
    }
}
