<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * [ID19] ★ Sanctum 認証・認可に関する応用自動テスト
 */
class Id19SanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 未認証時に書き込み系 API で 401 が返ることを検証 (AP06)
     *
     * @return void
     */
    public function test_unauthenticated_request_returns_401_json(): void
    {
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-10-12',
            'clock_in' => '09:00:00',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.'
            ]);
    }

    /**
     * 認証済みユーザーは自分の勤怠を更新・削除できることを検証
     *
     * @return void
     */
    public function test_authenticated_user_can_update_and_delete_own_record(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id, 'date' => '2026-10-12']);

        $updateResponse = $this->putJson('/api/v1/attendance-records/' . $record->id, [
            'date' => '2026-10-12',
            'clock_in' => '09:00:00',
        ]);
        $updateResponse->assertStatus(200);

        $deleteResponse = $this->deleteJson('/api/v1/attendance-records/' . $record->id);
        $deleteResponse->assertStatus(204);
    }

    /**
     * 他ユーザーの勤怠を更新・削除しようとすると 403 が返ることを検証 (AP04, AP05)
     *
     * @return void
     */
    public function test_cannot_modify_other_user_record_returns_403_json(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $recordOfUserB = AttendanceRecord::factory()->create(['user_id' => $userB->id, 'date' => '2026-10-12']);

        Sanctum::actingAs($userA, ['*']);

        $response = $this->putJson('/api/v1/attendance-records/' . $recordOfUserB->id, [
            'date' => '2026-10-12',
            'clock_in' => '09:00:00',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'この操作を実行する権限がありません。'
            ]);
    }
}
