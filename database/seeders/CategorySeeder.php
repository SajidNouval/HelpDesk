<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::create([
            'name' => 'Wifi',
            'description' => 'Masalah jaringan wifi seperti tidak terhubung atau lemot',
        ]);

        Category::create([
            'name' => 'Email',
            'description' => 'Masalah email seperti login gagal atau tidak bisa kirim',
        ]);

        Category::create([
            'name' => 'Internet',
            'description' => 'Masalah koneksi internet secara umum',
        ]);

        Category::create([
            'name' => 'Aplikasi',
            'description' => 'Masalah pada aplikasi internal atau software',
        ]);

        Category::create([
            'name' => 'Hardware',
            'description' => 'Masalah perangkat keras seperti komputer, printer, dll',
        ]);
    }
}
