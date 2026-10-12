<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\StampCorrectionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Id15AdminApplicationApprovalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 15 - 1: 承認待ちの修正申請が全て表示されているかの検証
     */
    public function test_id15_1_all_pending_applications_displayed(): void
    {
        $admin = User::create([
            'name' => '最終承認管理者',
            'email' => 'admin-approve-final@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        $staff = User::create([
            'name' => '申請提出スタッフ',
            'email' => 'staff-approve-final@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);

        $attendance = AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'applicant_id' => $staff->id,
            'proposed_clock_in' => '08:30:00',
            'proposed_clock_out' => '17:30:00',
            'proposed_comment' => 'テスト用の修正申請理由です',
            'status' => 0, // 0: 承認待ち
        ]);

        // 管理者用の申請一覧ページ（PG09）を開き、承認待ちタブの内容を検証
        $response = $this->actingAs($admin)->get('/stamp_correction_request/list');
        $response->assertStatus(200);
        $response->assertSee('テスト用の修正申請理由です');
        $response->assertSee('承認待ち');
    }

    /**
     * ID 15 - 2: 承認済みの修正申請が全て表示されているかの検証
     */
    public function test_id15_2_all_approved_applications_displayed(): void
    {
        $admin = User::create([
            'name' => '最終承認管理者',
            'email' => 'admin-approve-final@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        $staff = User::create([
            'name' => '申請提出スタッフ',
            'email' => 'staff-approve-final@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);

        $attendance = AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'applicant_id' => $staff->id,
            'proposed_clock_in' => '08:30:00',
            'proposed_clock_out' => '17:30:00',
            'proposed_comment' => '承認済みテスト用理由です',
            'status' => 1, // 1: 承認済み
        ]);

        // 管理者用の申請一覧ページを開き、承認済みタブの内容を検証
        $response = $this->actingAs($admin)->get('/stamp_correction_request/list');
        $response->assertStatus(200);
        $response->assertSee('承認済み');
    }

    /**
     * ID 15 - 3: 修正申請の詳細内容が正しく表示されているかの検証
     */
    public function test_id15_3_application_detail_content_integrity(): void
    {
        $admin = User::create([
            'name' => '最終承認管理者',
            'email' => 'admin-approve-final@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        $staff = User::create([
            'name' => '申請提出スタッフ',
            'email' => 'staff-approve-final@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);

        $attendance = AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $correctionRequest = StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'applicant_id' => $staff->id,
            'proposed_clock_in' => '08:30:00',
            'proposed_clock_out' => '17:30:00',
            'proposed_comment' => '詳細確認テスト理由です',
            'status' => 0,
        ]);

        // 管理者用の申請詳細（承認画面：PG10）を開き、内容を検証
        $response = $this->actingAs($admin)->get('/stamp_correction_request/approve/' . $correctionRequest->id);
        $response->assertStatus(200);
        $response->assertSee($staff->name);
        $response->assertSee('詳細確認テスト理由です');
    }

    /**
     * ID 15 - 4: 修正申請の承認処理が正しく行われるかの検証
     */
    public function test_id15_4_approval_process_updates_attendance(): void
    {
        $admin = User::create([
            'name' => '最終承認管理者',
            'email' => 'admin-approve-final@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
        ]);

        $staff = User::create([
            'name' => '申請提出スタッフ',
            'email' => 'staff-approve-final@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);

        $attendance = AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => \Carbon\Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $correctionRequest = StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'applicant_id' => $staff->id,
            'proposed_clock_in' => '08:30:00',
            'proposed_clock_out' => '17:30:00',
            'proposed_comment' => '最終テスト用の修正申請理由です',
            'status' => 0,
        ]);

        // 承認ボタン（POST）を実行し、一覧画面へリダイレクトされるか検証
        $response = $this->actingAs($admin)->post('/stamp_correction_request/approve/' . $correctionRequest->id);
        $response->assertStatus(302);

        // 大元の勤怠レコードが申請データ通りに上書きされているか検証
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
        ]);

        // 申請ステータスが「1: 承認済み」に更新されているか検証
        $this->assertDatabaseHas('stamp_correction_requests', [
            'id' => $correctionRequest->id,
            'status' => 1,
        ]);
    }
}
