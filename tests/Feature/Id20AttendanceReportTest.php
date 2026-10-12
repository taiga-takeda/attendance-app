<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * [ID20] ★ マイ勤怠レポート機能に関する応用自動テスト
 */
class Id20AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ゲストはレポートページにアクセスできないことを検証
     *
     * @return void
     */
    public function test_guest_cannot_access_report_page_redirects_to_login(): void
    {
        $response = $this->get('/attendance/report');
        $response->assertRedirect('/login');
    }

    /**
     * 認証ユーザーの統計情報が正しく計算されることを検証
     *
     * @return void
     */
    public function test_authenticated_user_report_statistics_are_calculated_correctly(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        
        $currentMonthStr = Carbon::now()->format('Y-m');
        $record = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => $currentMonthStr . '-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        $record->breaks()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);

        $response = $this->actingAs($user)->get('/attendance/report');
        
        $response->assertStatus(200)
            ->assertViewHasAll(['summary', 'monthlyTrend', 'anomalies']);
    }

    /**
     * 勤怠記録がないユーザーでも安全に処理されることを検証
     *
     * @return void
     */
    public function test_user_without_attendance_records_can_view_report_safely(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get('/attendance/report');

        $response->assertStatus(200)
            ->assertViewHas('summary');
    }
}
