<?php

namespace Database\Factories;

use App\Models\Certification;
use App\Models\Student;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificationFactory extends Factory
{
    protected $model = Certification::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'issued_by' => User::factory()->admin(),
            'issued_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'certificate_no' => 'CERT-' . fake()->unique()->bothify('####??'),
            'status' => fake()->randomElement(['active', 'revoked']),
        ];
    }
}
