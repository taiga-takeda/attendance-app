<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * [ID18] ★ 公開API 書き込み系に関する応用自動テスト
 */
class Id18ApiWriteValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * POST /api/v1/attendance-records で勤怠が新規作成されることを検証 (AP03)
     *
     * @return void
     */
    public function test_can_store_attendance_record_via_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-10-12',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'API登録テスト',
        ]);

        // 💡 期待挙動：HTTP 201 Created が返却されること
        $response->assertStatus(201);
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-10-12',
        ]);
    }

    /**
     * バリデーションエラー時に 422 と正しい日本語エラー構造が返ることを検証
     *
     * @return void
     */
    public function test_store_attendance_validation_fails_with_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // 💡 必須項目を空にしてバリデーションを故意に発生させます
        $response = $this->postJson('/api/v1/attendance-records', []);

        // 💡 期待挙動：HTTP 422 が返り、errors フィールドが正確に含まれていること
        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['date', 'clock_in']]);
    }

    /**
     * PUT /api/v1/attendance-records/{id} で勤怠が更新され、存在しない場合は404を検証 (AP04)
     *
     * @return void
     */
    public function test_can_update_and_handle_404_via_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id, 'date' => '2026-10-12']);

        // ① 正常な更新
        $response = $this->putJson('/api/v1/attendance-records/' . $record->id, [
            'date' => '2026-10-12',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);
        $response->assertStatus(200);

        // ② 存在しないIDへの更新（グローバル例外ハンドラー経由での404テスト）
        $notFoundResponse = $this->putJson('/api/v1/attendance-records/99999', [
            'date' => '2026-10-12',
            'clock_in' => '10:00:00',
        ]);
        $notFoundResponse->assertStatus(404);
    }

    /**
     * DELETE /api/v1/attendance-records/{id} で勤怠が削除され、存在しない場合は404を検証 (AP05)
     *
     * @return void
     */
    public function test_can_delete_and_handle_404_via_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);

        // ① 正常な削除
        $response = $this->deleteJson('/api/v1/attendance-records/' . $record->id);
        $response->assertStatus(204); // 💡 仕様書指定：204 No Content
        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);

        // ② 存在しないIDへの削除（グローバル例外ハンドラー経由での404テスト）
        $notFoundResponse = $this->deleteJson('/api/v1/attendance-records/99999');
        $notFoundResponse->assertStatus(404);
    }
}
