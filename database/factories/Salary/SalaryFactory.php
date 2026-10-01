<?php

namespace Database\Factories\Salary;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Salary\Salary>
 */
class SalaryFactory extends Factory
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
            'amount' => 5000000,
            'worked_minute' => 9600,
            'break_minute' => 0,
            'hour_price' => 31250,
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
            'comment' => 'Monthly salary',
        ];
    }
}
