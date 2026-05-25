<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * ARTICLE SEEDER REFACTOR (TF-IDF Optimization):
     * - Each article is EXPLICITLY assigned to the correct category (no random assignment)
     * - Domain-specific titles and content (no generic wording)
     * - Clear keyword separation between domains
     * - Balanced article count per category (5-8 articles each)
     * - Security domain isolated with dedicated articles
     * - No overlapping topics between categories
     */
    public function run(): void
    {
            // Get categories by name for explicit assignment
        $wifiJaringan = Category::where('name', 'Wifi & Jaringan')->first();
        $komputer = Category::where('name', 'Komputer')->first();
        $printer = Category::where('name', 'Printer')->first();
        $email = Category::where('name', 'Email')->first();
        $keamananSistem = Category::where('name', 'Keamanan Sistem')->first();
        $aplikasi = Category::where('name', 'Aplikasi')->first();

        // Get staff users for assignment
        $staffs = User::where('role', 'staff')->get();
        if ($staffs->isEmpty()) {
            $staffs = User::where('role', 'admin')->get();
        }

        // ============================================================
        // WIFI & JARINGAN (6 articles)
        // Domain: Network connectivity, wifi, router, internet connection
        // ============================================================
        $wifiArticles = [
            [
                "title" => "Cara Mengatasi Wifi Tidak Terhubung ke Perangkat",
                "content" => "Wifi yang tidak terhubung dapat disebabkan oleh beberapa faktor. Pertama, pastikan router dalam keadaan menyala dan lampu indikator berfungsi dengan baik. Kedua, cek apakah perangkat Anda dalam jangkauan signal wifi. Ketiga, coba restart router dengan mencabut kabel power selama 10 detik kemudian pasang kembali. Jika masih belum berhasil, coba lupakan jaringan wifi di pengaturan perangkat Anda kemudian sambung kembali dengan memasukkan password. Pastikan juga MAC address filtering tidak memblokir perangkat Anda.",
                "excerpt" => "Panduan lengkap mengatasi masalah wifi yang tidak terhubung ke perangkat Anda.",
                "keywords" => "wifi tidak terhubung, router wifi, signal wifi, restart router, MAC address filtering, jaringan wireless",
                'category_id' => $wifiJaringan->id,
            ],
            [
                "title" => "Solusi Internet Lambat pada Jaringan Wifi Kantor",
                "content" => "Internet lambat pada wifi kantor dapat mengganggu produktivitas. Penyebab umum: terlalu banyak perangkat terhubung ke access point, bandwidth terbagi untuk download/upload besar, atau interferensi channel wifi. Solusi: 1) Batasi jumlah perangkat per access point (maksimal 15-20 device), 2) Gunakan Quality of Service (QoS) untuk prioritaskan traffic penting, 3) Upgrade paket bandwidth dari ISP, 4) Gunakan kabel LAN untuk perangkat kritis seperti PC server, 5) Pindah ke frekuensi 5GHz untuk mengurangi interferensi.",
                "excerpt" => "Tips mengatasi internet lambat khusus untuk jaringan wifi kantor.",
                "keywords" => "internet lambat, bandwidth kantor, QoS router, access point, frekuensi 5GHz, ISP",
                'category_id' => $wifiJaringan->id,
            ],
            [
                "title" => "Cara Reset Router dan Konfigurasi Ulang Jaringan",
                "content" => "Reset router diperlukan saat konfigurasi bermasalah atau lupa password admin. Langkah reset: 1) Cari tombol reset di belakang router (biasanya lubang kecil), 2) Tekan tombol reset selama 10-15 detik menggunakan paperclip, 3) Tunggu router restart (semua lampu berkedip), 4) Akses halaman admin router di 192.168.1.1 atau 192.168.0.1, 5) Login dengan kredensial default (admin/admin atau lihat stiker router), 6) Konfigurasi ulang SSID, password wifi, dan pengaturan keamanan. Catatan: reset akan menghapus semua pengaturan custom.",
                "excerpt" => "Panduan reset router ke pengaturan pabrik dan konfigurasi ulang.",
                "keywords" => "reset router, konfigurasi router, admin router, 192.168.1.1, SSID, password default",
                'category_id' => $wifiJaringan->id,
            ],
            [
                "title" => "Mengatasi Wifi Sering Putus Nyambung pada Jaringan Kantor",
                "content" => "Wifi yang sering terputus-putus di kantor sangat mengganggu. Penyebab dan solusi: 1) Interferensi channel - ubah channel wifi ke channel 1, 6, atau 11 yang tidak overlap, 2) Jarak terlalu jauh dari access point - gunakan wifi extender atau mesh network, 3) Router overheating - pastikan ventilasi router baik dan tidak tertutup debu, 4) Driver network adapter usang - update driver wifi adapter di Device Manager, 5) Terlalu banyak perangkat - batasi koneksi atau upgrade ke access point enterprise, 6) Firmware router usang - update ke versi terbaru dari website manufacturer.",
                "excerpt" => "Solusi wifi tidak stabil yang sering terputus di lingkungan kantor.",
                "keywords" => "wifi putus nyambung, interferensi channel, wifi extender, mesh network, firmware router, access point enterprise",
                'category_id' => $wifiJaringan->id,
            ],
            [
                "title" => "Cara Mengamankan Jaringan Wifi dari Akses Tidak Sah",
                "content" => "Keamanan jaringan wifi kantor sangat penting untuk melindungi data perusahaan. Langkah pengamanan: 1) Gunakan enkripsi WPA3 atau WPA2-AES, hindari WEP yang sudah usang dan mudah diretas, 2) Ganti password wifi secara berkala (minimal 3 bulan sekali), 3) Gunakan password yang kuat minimal 12 karakter dengan kombinasi huruf besar, huruf kecil, angka, dan simbol, 4) Sembunyikan SSID network agar tidak terlihat di daftar wifi publik, 5) Aktifkan MAC address filtering untuk whitelist perangkat yang boleh connect, 6) Update firmware router secara rutin untuk patch keamanan, 7) Pisahkan network guest dari network internal perusahaan menggunakan VLAN.",
                "excerpt" => "Strategi pengamanan jaringan wifi perusahaan dari akses tidak sah.",
                "keywords" => "keamanan wifi, enkripsi WPA3, WPA2-AES, MAC address filtering, hide SSID, VLAN, network guest",
                'category_id' => $wifiJaringan->id,
            ],
            [
                "title" => "Troubleshooting Koneksi Internet Putus Nyambung dari ISP",
                "content" => "Koneksi internet yang sering putus dari provider ISP memerlukan troubleshooting sistematis. Langkah diagnosis: 1) Cek lampu indikator modem - jika lampu LOS/PON berkedip merah, ada gangguan dari ISP, 2) Test koneksi langsung ke modem menggunakan kabel LAN (bypass router) untuk pastikan masalah bukan dari router, 3) Ping gateway modem (biasanya 192.168.100.1) - jika timeout, masalah di modem atau kabel, 4) Cek apakah ada gangguan massal di area Anda dengan hubungi call center ISP, 5) Restart modem dengan mencabut power 30 detik, 6) Jika menggunakan fiber optic, pastikan kabel fiber tidak tertekuk tajam atau rusak.",
                "excerpt" => "Cara mendiagnosis dan mengatasi masalah koneksi internet dari provider ISP.",
                "keywords" => "internet putus, ISP gangguan, modem indicator, fiber optic, call center ISP, restart modem",
                'category_id' => $wifiJaringan->id,
            ],
        ];

        // ============================================================
        // KOMPUTER (7 articles)
        // Domain: PC, laptop, performa, hardware komputer, OS
        // ============================================================
        $komputerArticles = [
            [
                "title" => "Cara Mengatasi PC/Laptop Lemot dengan Optimasi Sistem",
                "content" => "PC atau laptop yang lemot dapat diatasi dengan optimasi sistem. Langkah-langkah: 1) Uninstall aplikasi yang tidak digunakan melalui Control Panel > Programs and Features, 2) Hapus file temporary dengan Disk Cleanup atau tekan Win+R > ketik %temp%, 3) Disable startup programs yang tidak perlu di Task Manager > Startup tab, 4) Defragment harddisk (khusus HDD, bukan SSD) melalui Defragment and Optimize Drives, 5) Tambah RAM jika kapasitas kurang dari 8GB, 6) Ganti HDD ke SSD untuk peningkatan performa signifikan, 7) Kurangi efek visual Windows di System Properties > Advanced > Performance Settings > Adjust for best performance.",
                "excerpt" => "Panduan lengkap optimasi PC/laptop agar tidak lemot dan lebih responsif.",
                "keywords" => "PC lemot, laptop lemot, optimasi Windows, startup programs, upgrade RAM, SSD, Disk Cleanup",
                'category_id' => $komputer->id,
            ],
            [
                "title" => "Cara Mengganti Thermal Paste CPU dan GPU Laptop",
                "content" => "Thermal paste yang kering menyebabkan laptop overheating dan throttling. Cara mengganti: 1) Siapkan thermal paste berkualitas (Arctic MX-4, Noctua NT-H1), 2) Matikan laptop dan lepas baterai, 3) Buka casing belakang laptop menggunakan obeng yang sesuai, 4) Lepas heatsink/fan assembly dengan membuka sekrup secara diagonal, 5) Bersihkan thermal paste lama menggunakan tissue dan isopropyl alcohol 90%+, 6) Oleskan thermal paste baru seukuran biji jagung di tengah CPU/GPU, 7) Pasang kembali heatsink dengan tekanan merata, 8) Test suhu menggunakan HWMonitor atau Core Temp. Lakukan penggantian setiap 2 tahun atau saat suhu mulai tinggi.",
                "excerpt" => "Panduan mengganti thermal paste untuk mengatasi laptop overheating.",
                "keywords" => "thermal paste, laptop overheating, CPU temperature, GPU temperature, heatsink, HWMonitor",
                'category_id' => $komputer->id,
            ],
            [
                "title" => "Solusi Blue Screen of Death (BSOD) pada Windows",
                "content" => "BSOD menandakan masalah serius di sistem Windows. Langkah troubleshooting: 1) Catat error code yang muncul (contoh: IRQL_NOT_LESS_OR_EQUAL, PAGE_FAULT_IN_NONPAGED_AREA), 2) Restart komputer dan masuk Safe Mode jika BSOD berulang, 3) Update atau rollback driver yang baru diinstall melalui Device Manager, 4) Scan RAM menggunakan Windows Memory Diagnostic (tekan Win+R > mdsched.exe), 5) Check harddisk dengan chkdsk /f /r di Command Prompt admin, 6) Scan malware menggunakan Windows Defender atau antivirus, 7) Update Windows ke versi terbaru, 8) Uninstall software yang baru diinstall, 9) Coba System Restore ke titik sebelumnya. BSOD sering disebabkan driver bermasalah, RAM rusak, atau harddisk bad sector.",
                "excerpt" => "Cara mengatasi error Blue Screen of Death di Windows dengan langkah lengkap.",
                "keywords" => "BSOD, blue screen, error code Windows, Memory Diagnostic, chkdsk, driver bermasalah, Safe Mode",
                'category_id' => $komputer->id,
            ],
            [
                "title" => "Cara Upgrade RAM dan SSD pada Laptop/Komputer",
                "content" => "Upgrade RAM dan SSD adalah cara paling efektif meningkatkan performa komputer. Langkah upgrade RAM: 1) Cek tipe RAM yang didukung (DDR3/DDR4) dan maksimal kapasitas di spesifikasi motherboard, 2) Beli RAM dengan frekuensi dan timing yang sama untuk dual channel, 3) Matikan komputer dan lepas kabel power, 4) Buka casing dan cari slot RAM, 5) Tekan clip pengunci di kedua sisi untuk melepas RAM lama, 6) Masukkan RAM baru dengan sudut 45 derajat lalu tekan hingga clip terkunci. Langkah upgrade SSD: 1) Clone data dari HDD ke SSD menggunakan software seperti Macrium Reflect atau Acronis, 2) Ganti HDD dengan SSD di bay yang sama, 3) Set SSD sebagai boot drive pertama di BIOS/UEFI, 4) Install ulang Windows jika cloning gagal.",
                "excerpt" => "Panduan upgrade RAM dan SSD untuk meningkatkan performa komputer.",
                "keywords" => "upgrade RAM, upgrade SSD, DDR4, dual channel, clone HDD, Macrium Reflect, BIOS boot order",
                'category_id' => $komputer->id,
            ],
            [
                "title" => "Mengatasi Laptop Overheating dan Fan Berisik",
                "content" => "Laptop yang cepat panas dan fan berisik menandakan sistem pendingin bermasalah. Solusi: 1) Bersihkan debu dari ventilasi dan fan menggunakan compressed air atau blower, 2) Ganti thermal paste jika sudah kering (minimal 2 tahun sekali), 3) Gunakan cooling pad dengan fan tambahan untuk sirkulasi udara lebih baik, 4) Hindari menggunakan laptop di atas permukaan empuk seperti kasur atau bantal yang menutupi ventilasi, 5) Kurangi beban kerja dengan menutup aplikasi yang tidak perlu di Task Manager, 6) Atur power plan ke \"Balanced\" atau \"Power Saver\" di Control Panel > Power Options, 7) Update BIOS dan driver chipset untuk optimasi fan control, 8) Monitor suhu dengan aplikasi seperti HWMonitor atau SpeedFan.",
                "excerpt" => "Tips mengatasi laptop yang cepat panas dan fan yang berisik.",
                "keywords" => "laptop overheating, fan berisik, cooling pad, thermal paste, compressed air, HWMonitor, power plan",
                'category_id' => $komputer->id,
            ],
            [
                "title" => "Cara Memilih Spesifikasi Komputer untuk Kebutuhan Kantor",
                "content" => "Memilih komputer untuk kantor perlu pertimbangan spesifikasi yang tepat. Rekomendasi berdasarkan penggunaan: 1) Admin/Office - Intel i3/i5 gen 10+, RAM 8GB, SSD 256GB, integrated graphics cukup, 2) Akuntansi/Finance - Intel i5, RAM 16GB, SSD 512GB, monitor dual screen untuk spreadsheet, 3) Desain Grafis - Intel i7/Ryzen 7, RAM 32GB, SSD 1TB + HDD 2TB, dedicated GPU (NVIDIA GTX/RTX), monitor color accurate, 4) Programming/Development - Intel i7/Ryzen 7, RAM 16-32GB, SSD 512GB-1TB, monitor resolusi tinggi. Prioritaskan SSD daripada HDD untuk kecepatan booting dan loading aplikasi. Budget 5-7 juta untuk admin, 10-15 juta untuk desain.",
                "excerpt" => "Panduan memilih spesifikasi komputer yang tepat untuk berbagai kebutuhan kantor.",
                "keywords" => "spesifikasi komputer, Intel i5, Intel i7, RAM 16GB, SSD, dedicated GPU, komputer kantor",
                'category_id' => $komputer->id,
            ],
            [
                'title' => 'Cara Backup dan Restore Data Windows dengan Windows Backup',
                'content' => 'Backup data penting untuk mencegah kehilangan data akibat hardware failure atau ransomware. Cara setup Windows Backup: 1) Buka Control Panel > System and Security > Backup and Restore (Windows 7), 2) Klik "Set up backup" di sebelah kanan, 3) Pilih lokasi backup (external harddisk atau network location), 4) Pilih "Let me choose" untuk seleksi manual folder penting (Documents, Pictures, Desktop, Downloads), 5) Atur jadwal backup (misal: setiap hari jam 2 pagi saat komputer idle), 6) Klik "Save settings and run backup". Untuk restore: buka Backup and Restore > "Restore my files". Alternatif: gunakan File History di Settings > Update & Security > Backup untuk backup kontinu.',
                'excerpt' => 'Panduan setup backup dan restore data otomatis menggunakan Windows Backup.',
                'keywords' => 'backup Windows, restore data, Windows Backup, File History, external harddisk, scheduled backup',
                'category_id' => $komputer->id,
            ],
        ];

        // ============================================================
        // PRINTER (6 articles)
        // Domain: Printer errors, offline, not responding, printing issues
        // ============================================================
        $printerArticles = [
            [
                'title' => 'Cara Mengatasi Printer Offline dan Tidak Terdeteksi',
                'content' => 'Printer yang statusnya offline dan tidak terdeteksi komputer dapat diatasi dengan langkah berikut: 1) Periksa koneksi kabel USB - pastikan tertancap kuat di kedua port, coba port USB lain di komputer, 2) Untuk printer network, pastikan IP address printer sama subnet dengan komputer (ping IP printer dari Command Prompt), 3) Set printer sebagai default device di Control Panel > Devices and Printers > klik kanan printer > Set as default printer, 4) Restart Print Spooler service (tekan Win+R > services.msc > cari Print Spooler > restart), 5) Update atau reinstall driver printer dari website manufacturer, 6) Hapus printer dari Devices and Printers lalu add kembali menggunakan Add a printer wizard.',
                'excerpt' => 'Solusi lengkap mengatasi printer yang status offline dan tidak terdeteksi komputer.',
                'keywords' => 'printer offline, printer tidak terdeteksi, kabel USB, IP address printer, Print Spooler, driver printer',
                'category_id' => $printer->id,
            ],
            [
                'title' => 'Troubleshooting Printer Tidak Mau Ngeprint (No Response)',
                'content' => "Printer yang tidak merespon perintah print memerlukan troubleshooting sistematis: 1) Periksa apakah printer menyala dan tidak ada error code di display printer, 2) Cek apakah ada kertas di tray dan tidak ada kertas macet (paper jam), 3) Pastikan tinta/toner tidak habis - cek level melalui printer properties, 4) Clear print queue yang macet - buka Devices and Printers > klik printer > See what's printing > Cancel all documents, 5) Restart printer dengan mencabut power 30 detik, 6) Test print dari printer properties (Printer Properties > General > Print Test Page), 7) Jika masih tidak bisa, uninstall driver printer sepenuhnya dan install ulang dari website manufacturer.",
                'excerpt' => 'Cara mengatasi printer yang tidak merespon perintah print.',
                'keywords' => 'printer tidak ngeprint, printer no response, paper jam, tinta habis, print queue, test print',
                'category_id' => $printer->id,
            ],
            [
                'title' => 'Cara Mengatasi Hasil Print Bergaris atau Tidak Jelas',
                'content' => 'Hasil print bergaris atau tidak jelas menandakan masalah di print head atau tinta. Untuk printer inkjet: 1) Jalankan print head cleaning melalui printer properties (Maintenance tab > Head Cleaning), 2) Lakukan nozzle check untuk cek kondisi nozzle, 3) Jika masih bergaris, lakukan deep cleaning (maksimal 3x berturut-turut), 4) Pastikan printer digunakan minimal 1x seminggu untuk mencegah tinta kering di print head, 5) Ganti cartridge jika tinta sudah sangat sedikit atau expired. Untuk printer laser: 1) Goyangkan toner cartridge secara horizontal untuk meratakan toner, 2) Bersihkan corona wire menggunakan cotton bud, 3) Ganti drum unit jika sudah aus (biasanya setelah 10.000-15.000 halaman), 4) Cek fuser unit jika hasil print mudah luntur.',
                'excerpt' => 'Solusi hasil print bergaris atau tidak jelas pada printer inkjet dan laser.',
                'keywords' => 'hasil print bergaris, print head cleaning, nozzle check, toner cartridge, drum unit, fuser',
                'category_id' => $printer->id,
            ],
            [
                'title' => 'Cara Mengatasi Kertas Macet (Paper Jam) di Printer',
                'content' => 'Kertas macet adalah masalah printer yang paling umum. Cara mengatasi: 1) Matikan printer dan cabut kabel power untuk keamanan, 2) Buka semua cover printer (depan, belakang, atas) sesuai manual printer, 3) Tarik kertas yang macet secara perlahan searah jalur kertas (jangan ditarik paksa berlawanan arah karena bisa merusak roller), 4) Cek apakah ada sobekan kertas yang tertinggal di dalam printer, 5) Periksa roller pickup - bersihkan dengan lap lembab jika licin atau berdebu, 6) Pastikan kertas di tray tidak terlalu penuh (maksimal 80% kapasitas tray), 7) Gunakan kertas dengan gramatur yang sesuai (70-80 gsm untuk printer biasa), 8) Setelah kertas keluar, nyalakan printer dan test print. Jika paper jam berulang, ganti roller pickup yang sudah aus.',
                'excerpt' => 'Panduan lengkap mengatasi kertas macet di printer dengan aman.',
                'keywords' => 'kertas macet, paper jam, roller pickup, tray printer, jalur kertas, sobekan kertas',
                'category_id' => $printer->id,
            ],
            [
                'title' => 'Cara Install Driver Printer di Windows 10/11',
                'content' => 'Install driver printer yang benar penting untuk fungsi optimal. Langkah install: 1) Download driver printer dari website manufacturer (HP, Canon, Epson, Brother) sesuai model printer dan versi Windows (32-bit atau 64-bit), 2) Jalankan file installer sebagai administrator (klik kanan > Run as Administrator), 3) Ikuti wizard instalasi - pilih koneksi USB atau Network sesuai jenis printer, 4) Untuk printer USB, sambungkan kabel USB saat diminta installer, 5) Untuk printer network, masukkan IP address printer saat diminta, 6) Setelah install selesai, lakukan test print, 7) Set sebagai default printer jika diperlukan. Alternatif: Windows Update bisa download driver otomatis, tapi driver dari manufacturer biasanya lebih lengkap fiturnya.',
                'excerpt' => 'Panduan install driver printer di Windows 10/11 dengan benar.',
                'keywords' => 'install driver printer, driver HP, driver Canon, driver Epson, Windows 10, Windows 11, printer USB, printer network',
                'category_id' => $printer->id,
            ],
            [
                "title" => "Cara Setting Printer Network (LAN/WiFi) di Kantor",
                "content" => "Setting printer network memungkinkan printer digunakan bersama di kantor. Langkah setting: 1) Pastikan printer dan komputer terhubung ke network/switch yang sama, 2) Set IP address static untuk printer (melalui menu printer > Network > TCP/IP) agar IP tidak berubah, contoh: 192.168.1.200, 3) Di komputer, buka Control Panel > Devices and Printers > Add a printer > 'The printer that I want isn't listed', 4) Pilih 'Add a printer using a TCP/IP address or hostname', 5) Masukkan IP address printer, 6) Windows akan detect printer dan install driver (atau pilih driver manual), 7) Beri nama printer yang deskriptif (contoh: 'Printer HR Lantai 2'), 8) Test print dari komputer. Untuk sharing printer via USB: enable 'Printer Sharing' di printer properties > Sharing tab.",
                "excerpt" => "Cara setting printer network/LAN untuk digunakan bersama di kantor.",
                "keywords" => "printer network, printer LAN, IP address static, TCP/IP, sharing printer, switch network",
                'category_id' => $printer->id,
            ],
        ];

        // ============================================================
        // EMAIL (6 articles)
        // Domain: Email issues, Gmail, Outlook, email configuration
        // ============================================================
        $emailArticles = [
            [
                "title" => "Cara Reset Password Email yang Lupa",
                "content" => "Lupa password email dapat diatasi dengan fitur reset password. Langkah-langkah: 1) Buka halaman login email provider Anda (Gmail, Outlook, Yahoo), 2) Klik link 'Forgot password' atau 'Lupa kata sandi', 3) Masukkan alamat email yang ingin direset, 4) Pilih metode verifikasi: email recovery, SMS ke nomor terdaftar, atau security question, 5) Masukkan kode verifikasi yang dikirim, 6) Buat password baru yang kuat (minimal 12 karakter, kombinasi huruf besar/kecil, angka, simbol), 7) Simpan password di password manager seperti LastPass atau Bitwarden. Untuk keamanan, aktifkan Two-Factor Authentication (2FA) setelah reset password. Jika tidak ada akses ke recovery email/phone, gunakan form account recovery yang disediakan provider.",
                "excerpt" => "Panduan step by step reset password email yang lupa dengan mudah.",
                "keywords" => "reset password email, lupa password, forgot password, email recovery, two-factor authentication, password manager",
                'category_id' => $email->id,
            ],
            [
                "title" => "Mengatasi Email yang Tidak Bisa Dikirim (Stuck in Outbox)",
                "content" => "Email yang tidak bisa dikirim dan stuck di Outbox dapat disebabkan beberapa hal. Solusi: 1) Periksa koneksi internet - pastikan stabil, 2) Cek ukuran lampiran tidak melebihi batas provider (Gmail: 25MB, Outlook: 20MB) - jika lebih, gunakan Google Drive/OneDrive link, 3) Pastikan alamat email tujuan benar (tidak typo), 4) Clear Outbox - hapus email yang stuck, restart aplikasi email, buat email baru, 5) Cek pengaturan SMTP server: untuk Gmail SMTP.gmail.com port 587 (TLS) atau 465 (SSL), 6) Pastikan autentikasi SMTP diaktifkan di pengaturan email client, 7) Disable sementara antivirus/firewall yang mungkin memblokir koneksi SMTP, 8) Coba kirim via webmail untuk pastikan masalah bukan dari akun.",
                "excerpt" => "Solusi lengkap untuk email yang tidak bisa dikirim atau stuck di Outbox.",
                "keywords" => "email tidak bisa dikirim, stuck outbox, SMTP server, lampiran email, autentikasi SMTP, webmail",
                'category_id' => $email->id,
            ],
            [
                "title" => "Cara Setting Email di Outlook untuk Akun Kantor",
                "content" => "Setting email kantor di Outlook memerlukan konfigurasi yang tepat. Langkah setting: 1) Buka Outlook > File > Add Account, 2) Masukkan alamat email kantor Anda, 3) Pilih 'Advanced options' > 'Let me set up my account manually', 4) Pilih tipe akun: IMAP (recommended) atau POP3, 5) Masukkan pengaturan server: Incoming mail (IMAP): mail.namakantor.com port 993 (SSL), Outgoing mail (SMTP): mail.namakantor.com port 587 (TLS), 6) Masukkan username (biasanya full email address) dan password, 7) Klik 'Next' dan Outlook akan test koneksi, 8) Selesai. Untuk Office 365: pilih 'Microsoft 365' dan login dengan akun perusahaan. Pastikan AutoDiscover berfungsi untuk konfigurasi otomatis.",
                "excerpt" => "Panduan setting email kantor di Microsoft Outlook dengan konfigurasi IMAP/SMTP.",
                "keywords" => "setting email Outlook, email kantor, IMAP, SMTP, Office 365, AutoDiscover, incoming mail server",
                'category_id' => $email->id,
            ],
            [
                "title" => "Mengatasi Email Tidak Masuk ke Inbox (Gmail/Outlook)",
                "content" => "Email yang tidak masuk ke inbox dapat disebabkan filter atau setting yang salah. Troubleshooting: 1) Cek folder Spam/Junk - email mungkin terfilter sebagai spam, 2) Cek folder Promotions/Social (khusus Gmail) - email mungkin terkategori di tab lain, 3) Periksa filter email yang dibuat - buka Settings > See all settings > Filters and Blocked Addresses, hapus filter yang memblokir, 4) Cek apakah pengirim di-block list, 5) Pastikan kuota email tidak penuh (Gmail 15GB shared dengan Google Drive), 6) Untuk email kantor, cek apakah ada rule di Exchange server yang redirect email, 7) Disable sementara email forwarding yang mungkin mengalihkan email ke alamat lain, 8) Minta pengirim cek apakah email tidak bounce back dengan error message.",
                "excerpt" => "Solusi email yang tidak masuk ke inbox pada Gmail atau Outlook.",
                "keywords" => "email tidak masuk, inbox kosong, spam filter, folder promotions, kuota email penuh, email forwarding",
                'category_id' => $email->id,
            ],
            [
                "title" => "Cara Setting Email di Smartphone Android dan iPhone",
                "content" => "Setting email di smartphone memudahkan akses email dimana saja. Untuk Android (Gmail app): 1) Buka Settings > Accounts > Add Account > Google/Email, 2) Masukkan alamat email dan password, 3) Untuk email non-Gmail, pilih 'Personal (IMAP)' atau 'Personal (POP3)', 4) Masukkan pengaturan server incoming (IMAP: imap.email.com port 993 SSL) dan outgoing (SMTP: smtp.email.com port 587 TLS), 5) Selesai. Untuk iPhone (Mail app): 1) Buka Settings > Mail > Accounts > Add Account, 2) Pilih provider (Google, Outlook, Yahoo) atau 'Other' untuk email custom, 3) Masukkan detail email, password, dan deskripsi, 4) Pilih data yang ingin disinkronkan (Mail, Contacts, Calendars), 5) Pastikan 'Fetch New Data' diaktifkan di Settings > Mail > Accounts > Fetch New Data untuk notifikasi real-time. Pilih 'Push' untuk Gmail atau 'Fetch' setiap 15 menit untuk email lain.",
                "excerpt" => "Panduan lengkap setting email di smartphone Android dan iPhone.",
                "keywords" => "email smartphone, Android, iPhone, IMAP, SMTP, fetch new data, push email, Gmail app",
                'category_id' => $email->id,
            ],
            [
                "title" => "Cara Mengamankan Akun Email dari Peretasan",
                "content" => "Keamanan email sangat penting karena email sering menjadi gateway ke akun lain. Langkah pengamanan: 1) Aktifkan Two-Factor Authentication (2FA) - Settings > Security > 2-Step Verification, gunakan Google Authenticator atau Authy, 2) Gunakan password yang kuat dan unik (minimal 16 karakter, jangan gunakan ulang password dari akun lain), 3) Cek aktivitas login terkini - Gmail: myaccount.google.com/security > Your devices, Outlook: account.microsoft.com/security > Sign-in activity, 4) Hapus akses aplikasi pihak ketiga yang tidak dikenal - Settings > Security > Third-party access, 5) Waspada phishing - jangan klik link di email mencurigakan, selalu cek URL sebelum login, 6) Gunakan password manager untuk generate dan store password unik per akun, 7) Setup recovery email dan phone number yang valid.",
                "excerpt" => "Strategi mengamankan akun email dari peretasan dan akses tidak sah.",
                "keywords" => "keamanan email, two-factor authentication, 2FA, password kuat, phishing, Google Authenticator, recovery email",
                'category_id' => $email->id,
            ],
        ];

        // ============================================================
        // KEAMANAN SISTEM (8 articles)
        // Domain: Ransomware, malware, virus, VPN, firewall, cybersecurity
        // ============================================================
        $securityArticles = [
            [
                'title' => 'Cara Mengatasi dan Mencegah Serangan Ransomware',
                'content' => 'Ransomware adalah malware yang mengenkripsi file dan meminta tebusan. Cara mengatasi jika terkena: 1) Segera isolasi komputer yang terinfeksi - disconnect dari network (cabut LAN/WiFi) untuk mencegah penyebaran ke komputer lain, 2) Identifikasi jenis ransomware menggunakan ID Ransomware (id-ransomware.malwarehunterteam.com) - upload sample file terenkripsi, 3) Cek apakah ada decryptor gratis di No More Ransom (nomoreransom.org), 4) Restore file dari backup yang bersih (pastikan backup tidak terinfeksi), 5) Format ulang komputer dan install Windows dari media bersih, 6) Jangan bayar tebusan - tidak ada jaminan file kembali dan mendanai kriminalitas. Pencegahan: backup 3-2-1 (3 copy, 2 media berbeda, 1 offsite), update Windows/software rutin, jangan buka attachment email mencurigakan, gunakan antivirus dengan ransomware protection.',
                'excerpt' => 'Panduan lengkap mengatasi dan mencegah serangan ransomware yang mengenkripsi file.',
                'keywords' => 'ransomware, malware enkripsi, decryptor, backup 3-2-1, isolasi komputer, No More Ransom, ID Ransomware',
                'category_id' => $keamananSistem->id,
            ],
            [
                'title' => 'Cara Mengenali dan Menghapus Virus Komputer',
                'content' => 'Virus komputer menunjukkan gejala: komputer lemot tiba-tiba, program berjalan sendiri, popup iklan muncul, file hilang atau berubah ekstensi. Cara menghapus: 1) Boot ke Safe Mode (tekan F8 saat startup atau Settings > Recovery > Advanced startup), 2) Hapus file temporary (tekan Win+R > %temp%), 3) Scan menggunakan Windows Defender Offline (Settings > Update & Security > Windows Security > Virus & threat protection > Scan options > Microsoft Defender Offline scan), 4) Gunakan malware scanner tambahan seperti Malwarebytes (gratis) untuk second opinion, 5) Check startup programs di Task Manager > Startup tab - disable yang mencurigakan, 6) Reset browser settings jika ada hijacker (Chrome: Settings > Reset settings), 7) Update Windows dan semua software ke versi terbaru. Pencegahan: jangan download software dari situs tidak resmi, hati-hati dengan USB drive asing.',
                'excerpt' => 'Cara mengenali gejala virus komputer dan langkah menghapusnya dengan tuntas.',
                'keywords' => 'virus komputer, malware, Windows Defender, Malwarebytes, Safe Mode, popup iklan, browser hijacker',
                'category_id' => $keamananSistem->id,
            ],
            [
                'title' => 'Perbedaan Malware, Virus, Trojan, dan Ransomware',
                'content' => 'Istilah keamanan siber sering tertukar. Penjelasan: 1) Virus - malware yang menempel pada file/program合法 dan menyebar saat file dijalankan, memerlukan user action, 2) Worm - malware yang menyebar sendiri melalui network tanpa user action, 3) Trojan - malware yang menyamar sebagai program合法 (contoh: fake antivirus, crack software), tidak bisa replicate sendiri, 4) Ransomware - malware yang mengenkripsi file dan meminta tebusan, 5) Spyware - malware yang memata-matai aktivitas user (keylogger, screen capture), 6) Adware - malware yang menampilkan iklan agresif, 7) Rootkit - malware yang menyembunyikan diri di level sistem operasi. Pencegahan umum: gunakan antivirus real-time protection, update software rutin, jangan klik link mencurigakan, backup data secara teratur.',
                'excerpt' => 'Memahami perbedaan jenis-jenis malware dan cara pencegahannya.',
                'keywords' => 'malware, virus, trojan, ransomware, worm, spyware, adware, rootkit, keylogger',
                'category_id' => $keamananSistem->id,
            ],
            [
                'title' => 'Cara Mengaktifkan dan Konfigurasi Firewall Windows',
                'content' => 'Firewall Windows Defender adalah lapisan pertahanan pertama terhadap serangan network. Cara konfigurasi: 1) Buka Windows Defender Firewall (Control Panel > System and Security > Windows Defender Firewall), 2) Pastikan firewall aktif untuk semua network profile (Domain, Private, Public), 3) Review inbound/outbound rules - block koneksi yang tidak perlu, 4) Untuk aplikasi tertentu, buat rule custom: Advanced settings > Inbound Rules > New Rule > Program > pilih exe file > Allow/Block connection > pilih network profile, 5) Enable "Block all incoming connections" untuk network publik (kafe, bandara), 6) Check firewall status secara rutin di Windows Security > Firewall & network protection. Untuk server, pertimbangkan firewall hardware tambahan. Jangan disable firewall kecuali untuk troubleshooting spesifik.',
                'excerpt' => 'Panduan mengaktifkan dan mengkonfigurasi Firewall Windows Defender dengan benar.',
                'keywords' => 'firewall Windows, Windows Defender Firewall, inbound rules, outbound rules, network profile, Advanced settings',
                'category_id' => $keamananSistem->id,
            ],
            [
                'title' => 'Cara Menggunakan VPN untuk Akses Jaringan Kantor yang Aman',
                'content' => 'VPN (Virtual Private Network) penting untuk akses aman ke jaringan kantor dari luar. Cara menggunakan VPN kantor: 1) Dapatkan kredensial VPN dari tim IT (username, password, server address), 2) Install VPN client yang direkomendasikan (OpenVPN, Cisco AnyConnect, FortiClient), 3) Import konfigurasi VPN (.ovpn file untuk OpenVPN), 4) Masukkan kredensial dan connect ke server VPN kantor, 5) Setelah terhubung, Anda dapat akses resource internal (file server, aplikasi internal, printer network) seolah-olah di kantor, 6) Disconnect VPN saat tidak digunakan untuk keamanan. Manfaat VPN: enkripsi semua traffic, menyembunyikan IP address, akses resource internal dari mana saja. Jangan gunakan VPN gratis untuk data sensitif perusahaan karena privasi tidak terjamin.',
                'excerpt' => 'Panduan menggunakan VPN untuk akses aman ke jaringan kantor dari remote.',
                'keywords' => 'VPN kantor, OpenVPN, Cisco AnyConnect, FortiClient, remote access, enkripsi traffic, jaringan internal',
                'category_id' => $keamananSistem->id,
            ],
            [
                'title' => 'Cara Mengamankan Wifi dari Pencurian Sinyal dan Akses Ilegal',
                'content' => 'Keamanan wifi penting untuk mencegah akses tidak sah ke network perusahaan. Langkah pengamanan: 1) Gunakan enkripsi WPA3 (terbaru) atau WPA2-AES, hindari WEP yang sudah bisa diretas dalam hitungan menit, 2) Ganti password wifi secara berkala (minimal 3 bulan sekali) dengan password kuat (minimal 16 karakter, kombinasi huruf, angka, simbol), 3) Sembunyikan SSID network (disable SSID broadcast) agar tidak terlihat di scan wifi, 4) Aktifkan MAC address filtering untuk whitelist hanya perangkat yang diizinkan connect, 5) Pisahkan network guest menggunakan VLAN terpisah dari network internal perusahaan, 6) Update firmware router/access point secara rutin untuk patch keamanan, 7) Matikan WPS (WiFi Protected Setup) karena rentan brute force, 8) Pantau perangkat yang terhubung melalui admin panel router, 9) Gunakan RADIUS server untuk autentikasi enterprise (802.1X).',
                'excerpt' => 'Strategi lengkap mengamankan jaringan wifi dari pencurian sinyal dan akses ilegal.',
                'keywords' => 'keamanan wifi, WPA3, WPA2-AES, MAC address filtering, hide SSID, VLAN, WPS, RADIUS, 802.1X',
                'category_id' => $keamananSistem->id,
            ],
            [
                'title' => 'Cara Mengaktifkan Windows Defender dan Antivirus Protection',
                'content' => 'Windows Defender adalah antivirus built-in Windows yang cukup efektif. Cara mengaktifkan dan optimasi: 1) Buka Windows Security (Start > Settings > Update & Security > Windows Security), 2) Pastikan "Virus & threat protection" aktif, 3) Klik "Manage settings" > pastikan Real-time protection ON, Cloud-delivered protection ON, Automatic sample submission ON, 4) Jalankan Full scan secara berkala (seminggu sekali), 5) Aktifkan "Controlled folder access" untuk ransomware protection (Virus & threat protection > Manage ransomware protection), 6) Tambahkan folder penting ke "Protected folders" agar tidak bisa dimodifikasi aplikasi tidak dikenal, 7) Check "Protection history" untuk melihat ancaman yang terdeteksi. Untuk perlindungan tambahan, gunakan Malwarebytes sebagai second opinion scanner (tidak sebagai real-time untuk menghindari konflik). Jangan install lebih dari satu antivirus real-time.',
                'excerpt' => 'Cara mengaktifkan dan mengoptimalkan Windows Defender untuk perlindungan maksimal.',
                'keywords' => 'Windows Defender, antivirus, real-time protection, ransomware protection, controlled folder access, Full scan',
                'category_id' => $keamananSistem->id,
            ],
            [
                'title' => 'Cara Mengenali dan Menghindari Serangan Phishing',
                'content' => 'Phishing adalah teknik social engineering untuk mencuri kredensial. Ciri-ciri email phishing: 1) Pengirim mencurigakan - cek alamat email asli (bukan nama display), contoh: support@bank-bca.com (palsu) vs support@bca.co.id (asli), 2) Urgensi berlebihan - "Akun Anda akan diblokir dalam 24 jam!", 3) Link mencurigakan - hover link untuk lihat URL asli, phishing sering menggunakan domain mirip (paypa1.com vs paypal.com), 4) Lampiran tidak diminta - invoice.zip, document.scr, 5) Kesalahan ejaan dan grammar. Cara menghindari: jangan klik link di email - ketik manual URL di browser, cek sertifikat SSL (gembok hijau), aktifkan 2FA, gunakan password manager yang auto-fill hanya di situs asli, laporkan email phishing ke provider. Jika terlanjur input data, segera ganti password dan monitor akun.',
                'excerpt' => 'Panduan mengenali ciri-ciri serangan phishing dan cara menghindarinya.',
                'keywords' => 'phishing, social engineering, email palsu, link mencurigakan, sertifikat SSL, two-factor authentication',
                'category_id' => $keamananSistem->id,
            ],
        ];

        // ============================================================
        // APLIKASI (6 articles)
        // Domain: Internal software, company applications, installation
        // ============================================================
        $aplikasiArticles = [
            [
                'title' => 'Cara Install Aplikasi Internal Perusahaan di Komputer Baru',
                'content' => 'Install aplikasi internal perusahaan memerlukan langkah khusus: 1) Pastikan Anda memiliki hak akses yang diperlukan - hubungi IT untuk request akses, 2) Download installer dari portal internal perusahaan (biasanya di intranet atau SharePoint), 3) Jalankan file installer sebagai administrator (klik kanan > Run as Administrator), 4) Ikuti wizard instalasi - pilih direktori install (biasanya C:\Program Files\NamaAplikasi), 5) Setelah selesai, restart komputer untuk register komponen, 6) Login menggunakan kredensial perusahaan (biasanya terintegrasi dengan Active Directory/LDAP), 7) Jika ada error "missing DLL" atau ".NET Framework required", install dependency yang diperlukan, 8) Hubungi tim IT jika aplikasi tidak bisa connect ke server database. Pastikan antivirus tidak memblokir proses instalasi dengan menambahkan exception.',
                'excerpt' => 'Panduan instalasi aplikasi internal perusahaan untuk karyawan baru.',
                'keywords' => 'install aplikasi internal, software perusahaan, Run as Administrator, Active Directory, LDAP, dependency',
                'category_id' => $aplikasi->id,
            ],
            [
                'title' => 'Troubleshooting Aplikasi Internal Tidak Bisa Login',
                'content' => 'Aplikasi internal yang tidak bisa login dapat disebabkan beberapa hal. Langkah troubleshooting: 1) Pastikan kredensial benar - cek username dan password (case-sensitive), 2) Cek koneksi network ke server - ping server aplikasi, 3) Pastikan akun Active Directory tidak locked/expired - hubungi IT untuk reset, 4) Clear cache aplikasi - hapus folder di %AppData%\NamaAplikasi, 5) Check log aplikasi untuk error detail (biasanya di folder install atau %ProgramData%), 6) Pastikan VPN connected jika akses dari luar kantor, 7) Update aplikasi ke versi terbaru - download dari portal internal, 8) Disable browser extension jika aplikasi berbasis web, 9) Coba browser berbeda (Chrome, Firefox, Edge) untuk aplikasi web-based. Jika masalah berlanjut, screenshot error message dan laporkan ke tim IT.',
                'excerpt' => 'Solusi lengkap untuk aplikasi internal yang tidak bisa login.',
                'keywords' => 'aplikasi internal, tidak bisa login, Active Directory, VPN, cache aplikasi, log error',
                'category_id' => $aplikasi->id,
            ],
            [
                'title' => 'Cara Update Aplikasi Internal ke Versi Terbaru',
                'content' => 'Update aplikasi internal penting untuk keamanan dan fitur baru. Langkah update: 1) Cek notifikasi update di aplikasi (biasanya Help > Check for Updates), 2) Download installer versi terbaru dari portal internal perusahaan, 3) Tutup aplikasi yang sedang berjalan (pastikan tidak ada proses di Task Manager), 4) Jalankan installer update - biasanya akan detect instalasi existing dan upgrade, 5) Backup data/configurasi jika diperlukan (export settings, backup database lokal), 6) Setelah update, test fungsi utama aplikasi, 7) Jika ada masalah, rollback ke versi sebelumnya (simpan installer versi lama). Untuk aplikasi berbasis web, update biasanya otomatis di server - cukup refresh browser (Ctrl+F5 untuk hard refresh). Selalu baca release notes untuk perubahan penting atau breaking changes.',
                'excerpt' => 'Panduan update aplikasi internal perusahaan ke versi terbaru dengan aman.',
                'keywords' => 'update aplikasi, upgrade software, release notes, backup konfigurasi, rollback, hard refresh',
                'category_id' => $aplikasi->id,
            ],
            [
                'title' => 'Cara Mengatasi Aplikasi Internal yang Sering Crash/Force Close',
                'content' => 'Aplikasi yang sering crash memerlukan diagnosa mendalam. Langkah troubleshooting: 1) Check Event Viewer untuk error detail (Windows Logs > Application), cari error dari aplikasi terkait, 2) Update .NET Framework ke versi terbaru (download dari Microsoft), 3) Update Visual C++ Redistributable (download package x86 dan x64 dari Microsoft), 4) Jalankan aplikasi sebagai administrator (klik kanan shortcut > Properties > Compatibility > Run as administrator), 5) Disable compatibility mode jika aktif, 6) Check RAM usage di Task Manager - jika tinggi, tambah RAM atau tutup aplikasi lain, 7) Scan file sistem Windows (sfc /scannow di Command Prompt admin), 8) Reinstall aplikasi - uninstall, restart, install ulang, 9) Check apakah ada konflik dengan antivirus - tambahkan exception. Untuk aplikasi web-based, clear browser cache dan cookies, disable extension.',
                'excerpt' => 'Solusi mengatasi aplikasi internal yang sering crash atau force close.',
                'keywords' => 'aplikasi crash, force close, Event Viewer, .NET Framework, Visual C++, compatibility mode, sfc scannow',
                'category_id' => $aplikasi->id,
            ],
            [
                'title' => 'Cara Setting Koneksi Database untuk Aplikasi Internal',
                'content' => 'Aplikasi internal biasanya memerlukan koneksi ke database server. Cara setting: 1) Dapatkan informasi koneksi dari tim IT (server address, database name, username, password), 2) Buka konfigurasi aplikasi (biasanya file .config atau .ini di folder install, atau menu Settings > Database), 3) Masukkan Server/Host: alamat IP atau hostname server database (contoh: 192.168.1.100 atau DBSERVER), 4) Masukkan Database Name: nama database yang akan digunakan, 5) Pilih Authentication: Windows Authentication (jika terintegrasi AD) atau SQL Authentication (input username/password), 6) Test connection - biasanya ada tombol "Test Connection", 7) Simpan konfigurasi dan restart aplikasi. Pastikan firewall tidak memblokir port database (SQL Server default 1433, MySQL default 3306). Jika koneksi gagal, ping server database dan pastikan VPN connected (jika dari luar kantor).',
                'excerpt' => 'Panduan setting koneksi database untuk aplikasi internal perusahaan.',
                'keywords' => 'koneksi database, SQL Server, MySQL, connection string, Windows Authentication, firewall port',
                'category_id' => $aplikasi->id,
            ],
            [
                'title' => 'Cara Mengatasi Error "Missing DLL" pada Aplikasi Windows',
                'content' => 'Error "Missing DLL" atau "DLL not found" terjadi saat aplikasi memerlukan library yang tidak ada. Solusi: 1) Install Visual C++ Redistributable - download dari Microsoft (install versi x86 dan x64, tahun 2015-2022), 2) Install .NET Framework - download versi terbaru dari Microsoft (biasanya .NET 4.8 atau .NET 6/7/8), 3) Update Windows ke versi terbaru (Settings > Update & Security > Windows Update), 4) Jalankan System File Checker (sfc /scannow di Command Prompt admin) untuk repair file sistem, 5) Reinstall aplikasi - uninstall, restart, install ulang, 6) Untuk DLL spesifik (msvcp140.dll, vcruntime140.dll), download dari situs resmi Microsoft, JANGAN dari situs DLL download pihak ketiga (bisa malware), 7) Check apakah aplikasi memerlukan dependency khusus (DirectX, Java Runtime) - baca dokumentasi aplikasi. Restart komputer setelah install dependency.',
                'excerpt' => 'Solusi lengkap mengatasi error missing DLL pada aplikasi Windows.',
                'keywords' => 'missing DLL, Visual C++ Redistributable, .NET Framework, sfc scannow, msvcp140, vcruntime, dependency',
                'category_id' => $aplikasi->id,
            ],
        ];

        // Combine all articles
        $allArticles = array_merge(
            $wifiArticles,
            $komputerArticles,
            $printerArticles,
            $emailArticles,
            $securityArticles,
            $aplikasiArticles
        );

        // Create each article with explicit category assignment
        foreach ($allArticles as $articleData) {
            // Generate slug from title
            $slug = Str::slug($articleData['title']);

            // Add index if slug already exists
            $originalSlug = $slug;
            $counter = 1;
            while (Article::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            Article::create([
                'category_id' => $articleData['category_id'],
                'staff_id' => $staffs->random()->id,
                'title' => $articleData['title'],
                'slug' => $slug,
                'content' => $articleData['content'],
                'excerpt' => $articleData['excerpt'],
                'keywords' => $articleData['keywords'],
                'views' => rand(50, 500),
                'is_published' => true,
                'is_hidden' => false,
                'publish_status' => 'approved',
                'created_at' => now()->subDays(rand(0, 90)),
                'updated_at' => now()->subDays(rand(0, 60)),
            ]);
        }
    }
}