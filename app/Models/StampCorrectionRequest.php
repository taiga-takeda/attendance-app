<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StampCorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_record_id',
        'applicant_id',
        'proposed_clock_in',
        'proposed_clock_out',
        'proposed_comment',
        'status',
        'approver_id',
        'approved_at',
    ];

    /**
     * 対象の勤怠レコードを取得
     */
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    /**
     * 申請した一般ユーザーを取得
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    /**
     * 承認した管理者ユーザーを取得
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * この申請に紐づく休憩の修正案一覧を取得
     */
    public function proposedRestRecords(): HasMany
    {
        return $this->hasMany(ProposedRestRecord::class);
    }
}
