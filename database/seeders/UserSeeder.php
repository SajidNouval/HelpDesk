<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * =========================================================================
 * SEEDER USER - SEEDER USER ADMIN DAN STAFF
 * =========================================================================
 *
 * Seeder ini membuat user admin dan staff untuk sistem helpdesk.
 *
 * User yang Dibuat:
 * - Admin Utama (admin@gmail.com): Role admin
 * - Staff Pelayanan (staff@gmail.com): Role staff
 * - Staff Pelayanan 2 (umam@gmail.com): Role staff
 * - Staff Pelayanan 3 (jamal@gmail.com): Role staff
 *
 * Catatan:
 * - Password default: 'password'
 * - Menggunakan updateOrCreate untuk menghindari duplicate
 */
class UserSeeder extends Seeder
{
    /**
     * Fungsi:
     * Menjalankan seeder untuk membuat user admin dan staff.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin Utama',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
            [
                'name' => 'Staff Pelayanan',
                'email' => 'staff@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ],
            [
                'name' => 'Staff Pelayanan 2',
                'email' => 'umam@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ],
            [
                'name' => 'Staff Pelayanan 3',
                'email' => 'jamal@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
