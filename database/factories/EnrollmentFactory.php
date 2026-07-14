<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'status' => fake()->randomElement(['pending', 'active', 'completed', 'cancelled']),
            'enrolled_at' => fake()->dateTimeBetween('-3 months', 'now'),
        ];
    }
}
