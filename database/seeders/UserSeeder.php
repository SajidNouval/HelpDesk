<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin Utama',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        User::factory()->create([
            'name' => 'Staff Pelayanan',
            'email' => 'staff@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
        ]);

        User::factory()->create([
            'name' => 'Staff Pelayanan 2',
            'email' => 'umam@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
        ]);

        User::factory()->create([
            'name' => 'Staff Pelayanan 3',
            'email' => 'jamal@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
        ]);
    }
}
