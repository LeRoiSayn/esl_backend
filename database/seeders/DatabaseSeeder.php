<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'username' => 'registrar1',
            'email' => 'registrar1@esl.local',
            'password' => Hash::make('password123'),
            'first_name' => 'Jean',
            'last_name' => 'Registrar',
            'role' => 'registrar',
            'phone' => '+241 01 23 45 69',
            'is_active' => true,
        ]);
    }
}
