<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => \App\Models\Department::factory(),
            'code' => strtoupper(fake()->unique()->lexify('???') . fake()->numberBetween(100, 999)),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'credits' => fake()->numberBetween(1, 6),
            'level' => fake()->randomElement(['L1', 'L2', 'L3', 'M1', 'M2']),
            'semester' => fake()->numberBetween(1, 3),
            'hours_per_week' => fake()->numberBetween(2, 6),
            'course_type' => 'tronc_commun',
            'is_active' => true,
        ];
    }
}
