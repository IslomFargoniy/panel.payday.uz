<?php

namespace Database\Factories\Worker;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Worker\Worker>
 */
class WorkerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'work_time' => '09:00',
            'end_time' => '18:00',
            'branch_id' => \App\Models\Branch\Branch::factory(),
            'employeeNoString' => (string) fake()->unique()->numberBetween(1000, 99999),
            'status' => 1,
        ];
    }
}
