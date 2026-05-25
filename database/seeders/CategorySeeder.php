<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Category Structure (Refactored for TF-IDF Retrieval):
     * - Clear domain separation to prevent retrieval collision
     * - No overlapping categories (removed "Internet" and "Hardware")
     * - Security domain isolated to prevent interference with other domains
     */
    public function run(): void
    {
        // 1. Wifi & Jaringan - Network connectivity domain
        Category::create([
            'name' => 'Wifi & Jaringan',
            'description' => 'Masalah jaringan wifi, router, koneksi internet, dan infrastruktur jaringan',
        ]);

        // 2. Komputer - PC and laptop hardware/software domain
        Category::create([
            'name' => 'Komputer',
            'description' => 'Masalah PC, laptop, performa komputer, hardware komputer, dan sistem operasi',
        ]);

        // 3. Printer - Dedicated printer domain
        Category::create([
            'name' => 'Printer',
            'description' => 'Masalah printer, error printer, printer offline, dan troubleshooting percetakan',
        ]);

        // 4. Email - Email communication domain
        Category::create([
            'name' => 'Email',
            'description' => 'Masalah email, Gmail, Outlook, email tidak masuk, dan konfigurasi email',
        ]);

        // 5. Keamanan Sistem - Security domain (isolated from other domains)
        Category::create([
            'name' => 'Keamanan Sistem',
            'description' => 'Masalah ransomware, malware, virus, VPN, firewall, dan keamanan siber',
        ]);

        // 6. Aplikasi - Internal software domain
        Category::create([
            'name' => 'Aplikasi',
            'description' => 'Masalah software internal, aplikasi perusahaan, dan instalasi program',
        ]);
    }
}