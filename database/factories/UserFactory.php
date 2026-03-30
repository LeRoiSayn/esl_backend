<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'student',
            'phone' => fake()->phoneNumber(),
            'is_active' => true,
            'status' => 'active',
            'employee_id' => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function student(): static
    {
        return $this->state(['role' => 'student']);
    }

    public function teacher(): static
    {
        return $this->state(['role' => 'teacher']);
    }

    public function finance(): static
    {
        return $this->state(['role' => 'finance']);
    }

    public function registrar(): static
    {
        return $this->state(['role' => 'registrar']);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
