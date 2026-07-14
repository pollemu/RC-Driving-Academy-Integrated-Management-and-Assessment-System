<?php

namespace Database\Factories;

use App\Models\Instructor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstructorFactory extends Factory
{
    protected $model = Instructor::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->instructor(),
            'phone' => fake()->phoneNumber(),
            'specialization' => fake()->randomElement(['TDC', 'PDC', 'TDC+PDC']),
            'license_no' => fake()->bothify('LIC-####??'),
        ];
    }
}
