<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\CategoryDomainKeyword;

/**
 * =========================================================================
 * SEEDER CATEGORY DOMAIN KEYWORD
 * =========================================================================
 *
 * Seeder ini mengisi kata kunci (keywords) awal untuk kategori yang ada.
 * Kata kunci ini akan digunakan oleh DomainDetectionService secara dinamis
 * untuk memetakan input query pengguna ke kategori yang sesuai.
 */
class CategoryDomainKeywordSeeder extends Seeder
{
    /**
     * Jalankan seeder.
     */
    public function run(): void
    {
        // Definisikan keywords awal per nama kategori
        $keywordsMap = [
            'Wifi & Jaringan' => [
                'wifi', 'wi-fi', 'wireless', 'wlan', 'hotspot', 'access point', 'ap', 
                'router', 'modem', 'jaringan', 'network', 'lan', 'wan', 'ethernet', 
                'kabel jaringan', 'switch', 'hub', 'koneksi', 'internet', 'bandwidth',
                'ping', 'rto', 'sinyal', 'ip address'
            ],
            'Komputer' => [
                'komputer', 'computer', 'pc', 'laptop', 'notebook', 'desktop', 
                'hardware', 'cpu', 'ram', 'processor', 'mainboard', 'motherboard', 
                'vga', 'harddisk', 'hdd', 'ssd', 'keyboard', 'mouse', 'monitor', 
                'blue screen', 'bsod', 'hang', 'slow', 'lemot', 'windows', 'os'
            ],
            'Printer' => [
                'printer', 'printing', 'cetak', 'mencetak', 'epson', 'canon', 
                'hp printer', 'ink', 'tinta', 'cartridge', 'toner', 'paper jam', 
                'macet', 'blink', 'offline', 'scanner', 'scan'
            ],
            'Email' => [
                'email', 'e-mail', 'surel', 'mail', 'gmail', 'outlook', 'yahoo mail', 
                'inbox', 'outbox', 'spam', 'send', 'receive', 'pop3', 'imap', 'smtp'
            ],
            'Keamanan Sistem' => [
                'keamanan', 'security', 'ransomware', 'malware', 'virus', 'trojan', 
                'spyware', 'adware', 'worm', 'rootkit', 'keylogger', 'phishing', 
                'backdoor', 'exploit', 'antivirus', 'windows defender', 'firewall', 
                'vpn', 'hack', 'hacker', 'password', 'sandi', 'login', 'credentials'
            ],
            'Aplikasi' => [
                'aplikasi', 'application', 'software', 'perangkat lunak', 'program', 
                'app', 'install', 'reinstall', 'uninstall', 'update', 'upgrade', 
                'crash', 'error', 'bug', 'database'
            ]
        ];

        foreach ($keywordsMap as $categoryName => $keywords) {
            $category = Category::where('name', $categoryName)->first();
            
            if ($category) {
                foreach ($keywords as $keyword) {
                    CategoryDomainKeyword::firstOrCreate([
                        'category_id' => $category->id,
                        'keyword' => mb_strtolower(trim($keyword))
                    ]);
                }
            }
        }
    }
}
