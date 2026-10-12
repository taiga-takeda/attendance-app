<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * [ID17] ★ 公開API 読み取り系に関する応用自動テスト
 */
class Id17ApiReadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 勤怠任意一覧が JSON で取得でき、meta情報が含まれることを検証
     *
     * @return void
     */
    public function test_can_get_attendance_list_with_meta_data(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->count(3)->create(['user_id' => $user->id]);

        $this->withoutExceptionHandling();

        $response = $this->getJson('/api/v1/attendance-records');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total']
            ]);
    }

    /**
     * 勤怠詳細が JSON で取得でき、ユーザー・休憩・修正申請が含まれることを検証
     *
     * @return void
     */
    public function test_can_get_attendance_detail_with_relations(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);
        $record->breaks()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);

        $response = $this->getJson('/api/v1/attendance-records/' . $record->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'user', 'breaks', 'applications']
            ]);
    }

    /**
     * 存在しない ID では 404 とエラー JSON が返ることを検証
     *
     * @return void
     */
    public function test_returns_404_error_json_for_non_existent_id(): void
    {
        $response = $this->getJson('/api/v1/attendance-records/99999');

        $response->assertStatus(404)
            ->assertJson([
                'error' => '勤怠情報が見つかりませんでした。'
            ]);
    }
}
