<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
    ];

    /**
     * この勤怠レコードを所有するユーザーを取得
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この勤怠レコードに紐づく休憩レコード一覧を取得
     */
    public function restRecords(): HasMany
    {
        return $this->hasMany(RestRecord::class);
    }

    /**
     * この勤怠レコードに対する修正申請一覧を取得
     */
    public function stampCorrectionRequests(): HasMany
    {
        return $this->hasMany(StampCorrectionRequest::class);
    }
    /**
     * 💡 【提供Blade無修正対応】大元のdateを壊さず、表示用として「10/11(日)」を返す安全なアクセサ
     */
    public function getFormattedDateAttribute()
    {
        if (!$this->attributes['date']) return '';
        \Carbon\Carbon::setLocale('ja');
        $carbonDate = \Carbon\Carbon::parse($this->attributes['date']);
        return $carbonDate->format('m/d') . '(' . $carbonDate->isoFormat('ddd') . ')';
    }
    // ==================================================
    // 🌐 【公開API仕様書完全適合：別名リレーション定義】
    // ==================================================
    // 💡 user() リレーションは既存のメソッドと完全に共通で使用するため、重複を避け安全に除外しました！
    
    /**
     * API仕様書（breaks）に完全適合させるリレーション（休憩レコード一覧）
     */
    public function breaks()
    {
        return $this->hasMany(RestRecord::class, 'attendance_record_id');
    }

    /**
     * API仕様書（applications）に完全適合させるリレーション（修正申請一覧）
     */
    public function applications()
    {
        return $this->hasMany(StampCorrectionRequest::class, 'attendance_record_id');
    }

}
