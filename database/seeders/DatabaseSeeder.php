<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * =========================================================================
 * SEEDER DATABASE - SEEDER UTAMA DATABASE
 * =========================================================================
 *
 * Seeder ini adalah seeder utama yang memanggil semua seeder lain.
 *
 * Seeder yang Dipanggil:
 * - UserSeeder: Membuat user admin dan staff
 * - CategorySeeder: Membuat kategori artikel
 * - ArticleSeeder: Membuat artikel sample
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Fungsi:
     * Menjalankan semua seeder untuk mengisi database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            CategoryDomainKeywordSeeder::class,
            ArticleSeeder::class,
        ]);
    }
}
