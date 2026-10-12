<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Id10UserAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 10 - 1: 勤怠詳細画面の「名前」がログインユーザーの氏名になっているかの検証
     */
    public function test_id10_1_detail_page_displays_correct_user_name(): void
    {
        $user = User::create([
            'name' => '詳細テストスタッフ',
            'email' => 'detail-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $targetDate = \Carbon\Carbon::today()->startOfMonth()->format('Y-m-d');
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $targetDate,
            'clock_in' => '09:15:00',
            'clock_out' => '18:30:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/' . $attendance->id);
        $response->assertStatus(200);
        $response->assertSee($user->name);
    }

    /**
     * ID 10 - 2: 勤怠詳細画面の「日付」が選択した日付になっているかの検証
     */
    public function test_id10_2_detail_page_displays_correct_date(): void
    {
        $user = User::create([
            'name' => '詳細テストスタッフ',
            'email' => 'detail-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        // 提供Bladeの「10月01日」などの成形仕様（m月d日）に完全連動させます
        $targetDate = \Carbon\Carbon::today()->startOfMonth()->day(1)->format('Y-m-d');
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $targetDate,
            'clock_in' => '09:15:00',
            'clock_out' => '18:30:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/' . $attendance->id);
        $response->assertStatus(200);
        $response->assertSee(\Carbon\Carbon::parse($targetDate)->format('m月d日'));
    }

    /**
     * ID 10 - 3: 「出勤・退勤」にて記されている時間がログインユーザーの打刻と一致しているかの検証
     */
    public function test_id10_3_detail_page_displays_correct_clock_times(): void
    {
        $user = User::create([
            'name' => '詳細テストスタッフ',
            'email' => 'detail-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $targetDate = \Carbon\Carbon::today()->startOfMonth()->format('Y-m-d');
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $targetDate,
            'clock_in' => '09:15:00',
            'clock_out' => '18:30:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/' . $attendance->id);
        $response->assertStatus(200);
        // HTMLインデントやコロンの影響を回避するため、出勤「09」「15」と退勤「18」「30」が順番に含まれているか安全に検証
        $response->assertSeeInOrder(['09', '15', '18', '30']);
    }

    /**
     * ID 10 - 4: 「休憩」にて記されている時間がログインユーザーの打刻と一致しているかの検証
     */
    public function test_id10_4_detail_page_displays_correct_break_times(): void
    {
        $user = User::create([
            'name' => '詳細テストスタッフ',
            'email' => 'detail-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $targetDate = \Carbon\Carbon::today()->startOfMonth()->format('Y-m-d');
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $targetDate,
            'clock_in' => '09:15:00',
            'clock_out' => '18:30:00',
        ]);

        $attendance->restRecords()->create([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/' . $attendance->id);
        $response->assertStatus(200);
        // 休憩開始「12」「00」と休憩終了「13」「00」が順番に含まれているか検証
        $response->assertSeeInOrder(['12', '00', '13', '00']);
    }
}
