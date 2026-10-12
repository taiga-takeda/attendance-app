<?php

namespace Database\Factories;

use App\Models\RestRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class RestRecordFactory extends Factory
{
    protected $model = RestRecord::class;

    public function definition(): array
    {
        return [
            'attendance_record_id' => \App\Models\AttendanceRecord::factory(),
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ];
    }
}
