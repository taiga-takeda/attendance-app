<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;


/**
 * 公開APIにおける勤怠レコードの更新・削除権限（認可）を制御するポリシー
 */
class AttendanceRecordPolicy
{
    /**
     * 【仕様書AP06：特権認可】
     * 管理者ユーザー（admin_status = true）である場合は、個別のチェックをすべて自動パスして全許可します
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->admin_status) {
            return true;
        }
        return null; // 一般ユーザーの場合は個別の認可メソッドへ流します
    }

    /**
     * 【仕様書AP04適合：更新認可】
     * 勤怠レコードの所有者（本人）のみ、更新を許可します。
     */
    public function update(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }

    /**
     * 【仕様書AP05適合：削除認可】
     * 勤怠レコードの所有者（本人）のみ、削除を許可します。
     */
    public function delete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }
}
