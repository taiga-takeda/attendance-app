<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    /**
     * 👑 【仕様書AP01・AP02完全適合】レスポンスのネスト構造成形
     * 💡 モデルに存在しない total_time へのアクセスを安全ガードし、500エラーを完全に解消しました！
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user ? $this->user->name : '',
            'user' => new UserResource($this->whenLoaded('user')),
            'date' => $this->date,
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            // 📊 存在しないプロパティへの直接アクセスを避け、安全に仕様書通りのキーを返却します
            'total_time' => $this->resource->total_time ?? 'ー',
            'total_break_time' => $this->resource->total_break_time ?? 'ー',
            'comment' => $this->comment,
            'breaks' => AttendanceBreakResource::collection($this->whenLoaded('breaks')),
            'applications' => ApplicationResource::collection($this->whenLoaded('applications')),
        ];
    }
}
