<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Id05AttendanceStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 05 - 1: 勤務外の場合 ➔ 画面上に表示されているステータスが「勤務外」となるか検証
     */
    public function test_id05_1_status_before_clock_in(): void
    {
        $user = User::create([
            'name' => 'ステータス確認スタッフ',
            'email' => 'status-check@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('勤務外');
    }

    /**
     * ID 05 - 2: 出勤中の場合 ➔ 画面上に表示されているステータスが「出勤中」となるか検証
     */
    public function test_id05_2_status_after_clock_in(): void
    {
        $user = User::create([
            'name' => 'ステータス確認スタッフ',
            'email' => 'status-check@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('出勤中');
    }

    /**
     * ID 05 - 3: 休憩中の場合 ➔ 画面上に表示されているステータスが「休憩中」となるか検証
     */
    public function test_id05_3_status_during_break(): void
    {
        $user = User::create([
            'name' => 'ステータス確認スタッフ',
            'email' => 'status-check@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
        ]);

        $attendance->restRecords()->create([
            'break_in' => '12:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩中');
    }

    /**
     * ID 05 - 4: 退勤済の場合 ➔ 画面上に表示されているステータスが「退勤済」となるか検証
     */
    public function test_id05_4_status_after_clock_out(): void
    {
        $user = User::create([
            'name' => 'ステータス確認スタッフ',
            'email' => 'status-check@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
        ]);

        $attendance->update([
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('退勤済');
    }
}
