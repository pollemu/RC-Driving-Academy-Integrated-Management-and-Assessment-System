<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Feedback;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition(): array
    {
        return [
            'feedback_id' => Feedback::factory(),
            'classification' => fake()->randomElement(['fast', 'average', 'needs']),
            'confidence_score' => fake()->randomFloat(2, 0.4, 0.99),
            'recommendation' => fake()->sentence(),
            'override_applied' => false,
        ];
    }
}
