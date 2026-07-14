<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Student;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'amount' => fake()->randomFloat(2, 500, 5000),
            'method' => fake()->randomElement(['gcash', 'bdo']),
            'reference_no' => fake()->bothify('REF-####??'),
            'paymongo_id' => null,
            'receipt_path' => null,
            'status' => fake()->randomElement(['pending', 'verified', 'rejected']),
            'verified_by' => null,
            'verified_at' => null,
        ];
    }
}
