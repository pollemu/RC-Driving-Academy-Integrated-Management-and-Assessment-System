<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'birthdate' => fake()->dateTimeBetween('-40 years', '-18 years')->format('Y-m-d'),
            'license_type' => fake()->randomElement(['non-pro', 'pro', 'student-permit']),
            'document_path' => null,
        ];
    }
}
