<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Id07BreakTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 07 - 1: 休憩ボタンが正しく機能するかの検証
     */
    public function test_id07_1_break_in_button_functionality(): void
    {
        $user = User::create([
            'name' => '休憩テストスタッフ',
            'email' => 'break-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertSee('休憩入');

        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertSee('休憩中');
    }

    /**
     * ID 07 - 2: 休憩は一日に何回でもできる（複数回の休憩入ボタン表示）の検証
     */
    public function test_id07_2_multiple_break_ins_allowed(): void
    {
        $user = User::create([
            'name' => '休憩テストスタッフ',
            'email' => 'break-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
        ]);

        // 1回目の休憩・戻り
        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);
        $this->actingAs($user)->post('/attendance', ['action' => 'break_out']);

        // 2回目も「休憩入」ボタンが正常に表示されるか検証
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertSee('休憩入');
    }

    /**
     * ID 07 - 3: 休憩戻ボタンが正しく機能するかの検証
     */
    public function test_id07_3_break_out_button_functionality(): void
    {
        $user = User::create([
            'name' => '休憩テストスタッフ',
            'email' => 'break-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
        ]);

        $attendance->restRecords()->create(['break_in' => '12:00:00']);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertSee('休憩戻');

        $this->actingAs($user)->post('/attendance', ['action' => 'break_out']);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertSee('出勤中');
    }

    /**
     * ID 07 - 4: 休憩戻は一日に何回でもできる（複数回の休憩戻ボタン表示）の検証
     */
    public function test_id07_4_multiple_break_outs_allowed(): void
    {
        $user = User::create([
            'name' => '休憩テストスタッフ',
            'email' => 'break-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
        ]);

        // 1回目の休憩・戻り、2回目の休憩入
        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);
        $this->actingAs($user)->post('/attendance', ['action' => 'break_out']);
        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);

        // 2回目も「休憩戻」ボタンが正常に表示されるか検証
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertSee('休憩戻');
    }

    /**
     * ID 07 - 5: 休憩時刻が勤怠一覧画面で確認できるかの検証
     */
    public function test_id07_5_break_time_reflected_on_list(): void
    {
        $user = User::create([
            'name' => '休憩テストスタッフ',
            'email' => 'break-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $attendance->restRecords()->create([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSeeInOrder(['01', '00', '00']);
    }
}
