<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StampCorrectionRequest extends FormRequest
{
    /**
     * 誰でもこのリクエストを送信できるように権限を true にします
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 修正申請のバリデーションルール定義
     */
    public function rules(): array
    {
        return [
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i'],
            'comment' => ['required', 'string', 'max:255'],
            // 休憩時間の入力（任意入力・形式チェック）
            'new_break_in' => ['nullable', 'array'],
            'new_break_out' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時間を入力してください',
            'new_clock_out.required' => '退勤時間を入力してください',
            'comment.required' => '備考を入力してください',
        ];
    }
}
