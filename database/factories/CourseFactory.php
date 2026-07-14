<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['TDC', 'PDC']);

        return [
            'name' => fake()->words(3, true),
            'type' => $type,
            'format' => fake()->randomElement(['Face to Face', 'Online', '4 Wheels', '2 Wheels']),
            'fee' => fake()->randomFloat(2, 500, 5000),
            'is_active' => true,
        ];
    }
}
