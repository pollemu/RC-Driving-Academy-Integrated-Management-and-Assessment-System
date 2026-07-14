<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\Course;
use App\Models\Instructor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'instructor_id' => Instructor::factory(),
            'date' => fake()->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
            'time_start' => fake()->time('H:i'),
            'time_end' => fake()->time('H:i'),
            'venue' => fake()->randomElement(['Room 1', 'Room 2', 'Driving Range A', 'Driving Range B']),
            'max_students' => fake()->numberBetween(1, 8),
            'status' => fake()->randomElement(['open', 'full', 'done']),
        ];
    }
}
