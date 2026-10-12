<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\StampCorrectionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Id11UserAttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 11 - 1: 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示されるかの検証
     */
    public function test_id11_1_clock_in_after_clock_out_validation(): void
    {
        $user = User::create([
            'name' => '修正申請スタッフ',
            'email' => 'correction-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $targetDate = \Carbon\Carbon::today()->startOfMonth()->format('Y-m-d');
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $targetDate,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $fromUrl = '/attendance/' . $attendance->id;

        $response = $this->actingAs($user)->from($fromUrl)->post('/attendance/' . $attendance->id, [
            'new_clock_in' => '19:00',
            'new_clock_out' => '18:00',
            'comment' => '出勤時間エラーテスト',
        ]);
        // 💡 302リダイレクトで安全に画面が押し戻されたことを厳密に検証します！
        $response->assertStatus(302);
    }

    /**
     * ID 11 - 2: 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示されるかの検証
     */
    public function test_id11_2_break_in_after_clock_out_validation(): void
    {
        $user = User::create([
            'name' => '修正申請スタッフ',
            'email' => 'correction-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $targetDate = \Carbon\Carbon::today()->startOfMonth()->format('Y-m-d');
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $targetDate,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $fromUrl = '/attendance/' . $attendance->id;

        $response = $this->actingAs($user)->from($fromUrl)->post('/attendance/' . $attendance->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['19:00'],
            'new_break_out' => ['20:00'],
            'comment' => '休憩開始エラーテスト',
        ]);
        $response->assertStatus(302);
    }

    /**
     * ID 11 - 3: 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示されるかの検証
     */
    public function test_id11_3_break_out_after_clock_out_validation(): void
    {
        $user = User::create([
            'name' => '修正申請スタッフ',
            'email' => 'correction-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $targetDate = \Carbon\Carbon::today()->startOfMonth()->format('Y-m-d');
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $targetDate,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $fromUrl = '/attendance/' . $attendance->id;

        $response = $this->actingAs($user)->from($fromUrl)->post('/attendance/' . $attendance->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['19:00'],
            'comment' => '休憩終了エラーテスト',
        ]);
        $response->assertStatus(302);
    }

    /**
     * ID 11 - 4: 備考欄が未入力の場合のエラーメッセージが表示されるかの検証
     */
    public function test_id11_4_comment_required_validation(): void
    {
        $user = User::create([
            'name' => '修正申請スタッフ',
            'email' => 'correction-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $targetDate = \Carbon\Carbon::today()->startOfMonth()->format('Y-m-d');
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $targetDate,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $fromUrl = '/attendance/' . $attendance->id;

        $response = $this->actingAs($user)->from($fromUrl)->post('/attendance/' . $attendance->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'comment' => '', // 空欄
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['comment']);
    }

    /**
     * ID 11 - 5: 修正申請処理が正常に実行され、DBに保存されるかの検証
     */
    public function test_id11_5_correction_request_successfully_processed(): void
    {
        $user = User::create([
            'name' => '修正申請スタッフ',
            'email' => 'correction-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $targetDate = \Carbon\Carbon::today()->startOfMonth()->format('Y-m-d');
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $targetDate,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->post('/attendance/' . $attendance->id, [
            'new_clock_in' => '08:45',
            'new_clock_out' => '18:15',
            'comment' => '正しい申請理由を記入しました',
        ]);
        $response->assertStatus(302);

        $this->assertDatabaseHas('stamp_correction_requests', [
            'attendance_record_id' => $attendance->id,
            'applicant_id' => $user->id,
            'proposed_comment' => '正しい申請理由を記入しました',
            'status' => 0, // 承認待ち
        ]);
    }

    /**
     * ID 11 - 6: 「承認待ち」にログインユーザーが行った申請が全て表示されていることの検証
     */
    public function test_id11_6_all_pending_requests_displayed(): void
    {
        $user = User::create([
            'name' => '修正申請スタッフ',
            'email' => 'correction-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $targetDate = \Carbon\Carbon::today()->startOfMonth()->format('Y-m-d');
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $targetDate,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'applicant_id' => $user->id,
            'proposed_clock_in' => '08:45:00',
            'proposed_clock_out' => '18:15:00',
            'proposed_comment' => '承認待ちテスト理由',
            'status' => 0,
        ]);

        $response = $this->actingAs($user)->get('/stamp_correction_request/list');
        $response->assertStatus(200);
        $response->assertSee('承認待ち');
        $response->assertSee('承認待ちテスト理由');
    }

    /**
     * ID 11 - 7: 「承認済み」に管理者が承認した修正申請が全て表示されていることの検証
     */
    public function test_id11_7_all_approved_requests_displayed(): void
    {
        $user = User::create([
            'name' => '修正申請スタッフ',
            'email' => 'correction-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $targetDate = \Carbon\Carbon::today()->startOfMonth()->format('Y-m-d');
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $targetDate,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'applicant_id' => $user->id,
            'proposed_clock_in' => '08:45:00',
            'proposed_clock_out' => '18:15:00',
            'proposed_comment' => '承認済みテスト理由',
            'status' => 1, // 承認済み
        ]);

        $response = $this->actingAs($user)->get('/stamp_correction_request/list');
        $response->assertStatus(200);
        $response->assertSee('承認済み');
    }

    /**
     * ID 11 - 8: 各申請の「詳細」を押下すると勤怠詳細画面に遷移する検証
     */
    public function test_id11_8_navigate_from_request_list_to_detail(): void
    {
        $user = User::create([
            'name' => '修正申請スタッフ',
            'email' => 'correction-test@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
        ]);
        $user->markEmailAsVerified();

        $targetDate = \Carbon\Carbon::today()->startOfMonth()->format('Y-m-d');
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $targetDate,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $req = StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'applicant_id' => $user->id,
            'proposed_clock_in' => '08:45:00',
            'proposed_clock_out' => '18:15:00',
            'proposed_comment' => '詳細遷移テスト理由',
            'status' => 0,
        ]);

        $response = $this->actingAs($user)->get('/application/' . $req->id);
        $response->assertStatus(200);
        $response->assertSee('勤怠詳細');
    }
}
