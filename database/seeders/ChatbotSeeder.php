<?php

namespace Database\Seeders;

use App\Models\Chatbot;
use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * =========================================================================
 * SEEDER CHATBOT - SEEDER RULES CHATBOT
 * =========================================================================
 *
 * Seeder ini membuat rules chatbot untuk routing query ke kategori.
 *
 * Rules yang Dibuat:
 * - Wifi/internet/lemot/putus/sinyal/jaringan/koneksi -> Kategori Wifi
 * - Email/outlook/gmail/kirim email/terima email/spam -> Kategori Email
 * - Aplikasi/software/install/download/crash/error -> Kategori Aplikasi
 * - Hardware/komputer/monitor/keyboard/mouse/device/perangkat -> Kategori Hardware
 * - Password/login/akses/lupa password/reset password/tidak bisa login -> Kategori Wifi
 *
 * Catatan:
 * - Setiap rule memiliki priority untuk ranking
 * - Response template untuk setiap kategori
 */
class ChatbotSeeder extends Seeder
{
    /**
     * Fungsi:
     * Menjalankan seeder untuk membuat rules chatbot.
     */
    public function run(): void
    {
        $categories = [
            'Wifi' => Category::firstOrCreate([
                'name' => 'Wifi',
            ], [
                'description' => 'Masalah jaringan wifi seperti tidak terhubung atau lemot',
            ])->id,
            'Email' => Category::firstOrCreate([
                'name' => 'Email',
            ], [
                'description' => 'Masalah email seperti login gagal atau tidak bisa kirim',
            ])->id,
            'Aplikasi' => Category::firstOrCreate([
                'name' => 'Aplikasi',
            ], [
                'description' => 'Masalah pada aplikasi internal atau software',
            ])->id,
            'Hardware' => Category::firstOrCreate([
                'name' => 'Hardware',
            ], [
                'description' => 'Masalah perangkat keras seperti komputer, printer, dll',
            ])->id,
        ];

        $rules = [
            [
                'keywords' => 'wifi,internet,lemot,putus,sinyal,jaringan,koneksi',
                'response' => 'Kami telah mengumpulkan beberapa artikel tentang masalah WiFi dan internet yang mungkin dapat membantu Anda. Silakan cek artikel di bawah ini.',
                'category_id' => $categories['Wifi'],
                'priority' => 100,
            ],
            [
                'keywords' => 'email,outlook,gmail,kirim email,terima email,spam',
                'response' => 'Kami menemukan beberapa artikel yang relevan dengan masalah email Anda. Silakan lihat di bawah:',
                'category_id' => $categories['Email'],
                'priority' => 90,
            ],
            [
                'keywords' => 'aplikasi,software,install,download,crash,error',
                'response' => 'Berikut beberapa panduan untuk mengatasi masalah software dan aplikasi Anda:',
                'category_id' => $categories['Aplikasi'],
                'priority' => 85,
            ],
            [
                'keywords' => 'hardware,komputer,monitor,keyboard,mouse,device,perangkat',
                'response' => 'Silakan cek artikel-artikel berikut yang mungkin dapat membantu masalah hardware Anda:',
                'category_id' => $categories['Hardware'],
                'priority' => 80,
            ],
            [
                'keywords' => 'password,login,akses,lupa password,reset password,tidak bisa login',
                'response' => 'Kami memiliki panduan untuk masalah password dan akses. Silakan lihat artikel di bawah:',
                'category_id' => $categories['Wifi'],
                'priority' => 99,
            ],
        ];

        foreach ($rules as $rule) {
            Chatbot::create($rule);
        }

        $this->command->info('Chatbot rules berhasil ditambahkan!');
    }
}