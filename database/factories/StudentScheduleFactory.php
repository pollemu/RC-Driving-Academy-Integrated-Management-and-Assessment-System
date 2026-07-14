<?php

namespace Database\Factories;

use App\Models\StudentSchedule;
use App\Models\Student;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentScheduleFactory extends Factory
{
    protected $model = StudentSchedule::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'schedule_id' => Schedule::factory(),
            'booked_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
