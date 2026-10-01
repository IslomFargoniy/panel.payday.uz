<?php

namespace Database\Factories\Salary;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Salary\SalaryPayment>
 */
class SalaryPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User\User::factory(),
            'worker_id' => \App\Models\Worker\Worker::factory(),
            'amount' => 1000000,
            'comment' => 'Advance payment',
        ];
    }
}
