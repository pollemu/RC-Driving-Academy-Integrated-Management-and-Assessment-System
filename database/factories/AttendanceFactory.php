<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'student_id' => Student::factory(),
            'status' => fake()->randomElement(['present', 'absent', 'late']),
            'notes' => fake()->sentence(),
            'recorded_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
