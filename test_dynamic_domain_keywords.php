<?php

/**
 * Test script for DYNAMIC DOMAIN KEYWORDS
 * 
 * Verifikasi:
 * 1. Loading keyword dari database.
 * 2. Deteksi domain bawaan (e.g. wifi, printer).
 * 3. Menambahkan kategori baru + keyword baru secara dinamis, dan chatbot mendeteksinya secara langsung (cache invalidation).
 * 4. Backward compatibility.
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\Chatbot\DomainDetectionService;
use App\Models\Category;
use App\Models\CategoryDomainKeyword;
use Illuminate\Support\Facades\Cache;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "==================================================\n";
echo "DYNAMIC DOMAIN KEYWORDS INTEGRATION TEST\n";
echo "==================================================\n\n";

// Clear cache before starting the test to ensure fresh start
$domainDetector = app(DomainDetectionService::class);
$domainDetector->clearCache();

echo "1. Menguji domain default dari seeder...\n";
echo "--------------------------------------------------\n";
$testCases = [
    'wifi lemot' => 'wifi',
    'kabel jaringan putus' => 'jaringan',
    'printer macet total' => 'printer',
    'layar komputer berkedip' => 'komputer',
    'email spam menumpuk' => 'email',
    'kena virus ransom' => 'security',
    'aplikasi crash melulu' => 'aplikasi',
];

foreach ($testCases as $query => $expectedDomain) {
    $result = $domainDetector->detectDomain($query);
    $detected = $result['detected'] ? 'TRUE' : 'FALSE';
    $primary = $result['domain'] ?? 'none';
    
    // Khusus 'kabel jaringan putus' boleh wifi atau jaringan
    if ($query === 'kabel jaringan putus' && ($primary === 'wifi' || $primary === 'jaringan')) {
        $status = '✅ OK';
    } else {
        $status = ($result['detected'] && $primary === $expectedDomain) ? '✅ OK' : '❌ FAIL';
    }
    
    echo "Query: \"{$query}\" -> Detected: {$detected}, Domain: {$primary} (Expected: {$expectedDomain}) - {$status}\n";
    if ($status === '❌ FAIL') {
        echo "   Debug Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    }
}

echo "\n2. Menguji deteksi out-of-domain...\n";
echo "--------------------------------------------------\n";
$outQuery = 'cara masak nasi goreng kambing';
$outResult = $domainDetector->detectOutOfDomain($outQuery);
if ($outResult['is_out_of_domain']) {
    echo "✅ Berhasil mendeteksi out-of-domain untuk: \"{$outQuery}\" (Reason: {$outResult['reason']})\n";
} else {
    echo "❌ Gagal mendeteksi out-of-domain untuk: \"{$outQuery}\"\n";
}

echo "\n3. Menambah kategori baru dan keyword baru secara dinamis...\n";
echo "--------------------------------------------------\n";

// Buat kategori baru: "Server & Hosting"
$newCategory = Category::firstOrCreate([
    'name' => 'Server & Hosting',
], [
    'description' => 'Masalah terkait server lokal, cPanel, VPS, dan hosting web'
]);

echo "Kategori baru dibuat: ID={$newCategory->id}, Name={$newCategory->name}\n";

// Tambahkan keyword dinamis untuk kategori baru ini
$newKeywords = ['cpanel', 'hosting', 'vps', 'domain name', 'nameserver', 'web hosting'];
foreach ($newKeywords as $kw) {
    CategoryDomainKeyword::firstOrCreate([
        'category_id' => $newCategory->id,
        'keyword' => $kw,
    ]);
}
echo "Keyword ditambahkan ke database: " . implode(', ', $newKeywords) . "\n";

// Karena CategoryObserver dipasang, cache chatbot:domain:dynamic_keywords seharusnya otomatis terhapus!
// Let's resolve DomainDetectionService again (as a singleton, we want a fresh load or we check cache).
// Kita bersihkan cache secara eksplisit atau biarkan observer yang bekerja.
// Di Laravel, saat Observer memicu clearCache, instance DomainDetectionService yang sudah diselesaikan
// di request ini mungkin masih menyimpan $domainKeywords lama di memory property.
// Tapi untuk request HTTP baru (atau jika diselesaikan ulang), service akan membaca DB lagi.
// Mari kita buat instance baru dari DomainDetectionService dengan mereset singleton binding.
app()->forgetInstance(DomainDetectionService::class);
$freshDomainDetector = app(DomainDetectionService::class);

echo "\nMenguji query dengan keyword kategori baru...\n";
$newQuery = 'vps lemot dan cpanel tidak bisa login';
$newResult = $freshDomainDetector->detectDomain($newQuery);
$newPrimary = $newResult['domain'] ?? 'none';

if ($newResult['detected'] && $newPrimary === 'server & hosting') {
    echo "✅ BERHASIL: Kategori baru langsung terdeteksi sebagai domain 'server & hosting'!\n";
    echo "Hasil deteksi: " . json_encode($newResult, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "❌ GAGAL: Kategori baru TIDAK terdeteksi. Domain yang terdeteksi: '{$newPrimary}'\n";
    echo "Detail deteksi: " . json_encode($newResult, JSON_PRETTY_PRINT) . "\n";
}

// Cek apakah vocabulary IT bertambah untuk "server & hosting"
echo "\nMenguji deteksi vocabulary IT untuk kata kunci baru...\n";
$vocabResult = $freshDomainDetector->detectOutOfDomain('cpanel hosting');
if (!$vocabResult['is_out_of_domain']) {
    echo "✅ BERHASIL: Kata kunci baru ('cpanel hosting') dikenali sebagai IT domain vocabulary!\n";
} else {
    echo "❌ GAGAL: Kata kunci baru dianggap out-of-domain: {$vocabResult['reason']}\n";
}

// Clean up: Hapus kategori baru untuk menjaga integritas DB
echo "\n4. Melakukan clean up data testing...\n";
echo "--------------------------------------------------\n";
$newCategory->delete();
echo "Kategori baru dihapus dari database.\n";

// Reset cache kembali
app()->forgetInstance(DomainDetectionService::class);
$domainDetectorAfterCleanup = app(DomainDetectionService::class);

echo "\nVerifikasi setelah cleanup...\n";
$cleanupResult = $domainDetectorAfterCleanup->detectDomain($newQuery);
$cleanupPrimary = $cleanupResult['domain'] ?? 'none';
if (!$cleanupResult['detected'] || $cleanupPrimary !== 'server & hosting') {
    echo "✅ Clean up sukses! Kategori baru tidak lagi terdeteksi.\n";
} else {
    echo "❌ Clean up gagal: Kategori masih terdeteksi.\n";
}

echo "\n==================================================\n";
echo "INTEGRATION TEST SELESAI\n";
echo "==================================================\n";
