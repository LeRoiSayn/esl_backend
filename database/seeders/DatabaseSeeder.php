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
            'email' => 'diallogloire+@gmail.com',
            'password' => Hash::make('password123'),
            'first_name' => 'Alex',
            'last_name' => 'Akounga',
            'role' => 'registrar',
            'phone' => '+241 01 23 45 69',
            'is_active' => true,
        ]);
    }
}
