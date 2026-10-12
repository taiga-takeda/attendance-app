<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $attendanceRecord = $this->route('attendanceRecord');
        $recordId = $attendanceRecord instanceof \App\Models\AttendanceRecord ? $attendanceRecord->id : $attendanceRecord;
        $userId = $this->user() ? $this->user()->id : null;

        return [
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendance_records', 'date')->ignore($recordId)->where('user_id', $userId),
            ],
            'clock_in' => ['required', 'date_format:H:i:s'],
            'clock_out' => ['nullable', 'date_format:H:i:s', 'after:clock_in'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }
}
