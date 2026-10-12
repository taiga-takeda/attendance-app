<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposedRestRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'stamp_correction_request_id',
        'rest_record_id',
        'proposed_break_in',
        'proposed_break_out',
    ];

    /**
     * この休憩修正案が属する修正申請を取得
     */
    public function stampCorrectionRequest(): BelongsTo
    {
        return $this->belongsTo(StampCorrectionRequest::class);
    }

    /**
     * 修正対象となっている元の休憩レコードを取得
     */
    public function restRecord(): BelongsTo
    {
        return $this->belongsTo(RestRecord::class);
    }
}
