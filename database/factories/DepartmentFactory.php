<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'faculty_id' => \App\Models\Faculty::factory(),
            'name' => fake()->unique()->word() . ' Department',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'is_active' => true,
        ];
    }
}
