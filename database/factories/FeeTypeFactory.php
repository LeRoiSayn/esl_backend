<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FeeType>
 */
class FeeTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true) . ' Fee',
            'description' => fake()->sentence(),
            'amount' => fake()->numberBetween(10000, 500000),
            'is_mandatory' => fake()->boolean(),
            'is_active' => true,
            'level' => fake()->randomElement(['L1', 'L2', 'L3', 'M1', 'M2', null]),
        ];
    }
}
