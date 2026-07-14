<?php

namespace Database\Factories;

use App\Models\Feedback;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeedbackFactory extends Factory
{
    protected $model = Feedback::class;

    public function definition(): array
    {
        return [
            'instructor_id' => Instructor::factory(),
            'student_id' => Student::factory(),
            'schedule_id' => Schedule::factory(),
            'raw_text' => fake()->paragraph(),
            'star_steering' => fake()->numberBetween(1, 5),
            'star_speed' => fake()->numberBetween(1, 5),
            'star_lane' => fake()->numberBetween(1, 5),
            'star_parking' => fake()->numberBetween(1, 5),
            'star_safety' => fake()->numberBetween(1, 5),
            'star_mirror' => fake()->numberBetween(1, 5),
        ];
    }
}
