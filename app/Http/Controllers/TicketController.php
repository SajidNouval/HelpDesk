<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Mail\TicketOtpMail;
use App\Mail\TicketTrackingMail;
use App\Models\Ticket;
use App\Models\Category;
use App\Models\Setting;
use App\Models\StaffProfile;
use App\Models\TicketLog;
use App\Models\TicketOtp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * =========================================================================
 * TICKET CONTROLLER - PENGELOLAAN TIKET
 * =========================================================================
 *
 * Controller ini menangani pembuatan, validasi, dan pelacakan tiket.
 *
 * Fitur Utama:
 * - Menyediakan form tiket untuk pengguna tamu
 * - Menyimpan tiket dan laporan ke database
 * - Mengelola proses OTP untuk verifikasi tiket
 * - Menyediakan fitur pelacakan tiket dan pembaruan status
 * - Auto-assign tiket ke staff yang tersedia
 *
 * Model Terkait:
 * - Ticket: Model tiket
 * - TicketOtp: Model OTP verifikasi
 * - TicketLog: Log aktivitas tiket
 * - StaffProfile: Profil staff
 */
class TicketController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE CREATE - TAMPILKAN FORM TIKET
     * =========================================================================
     *
     * Fungsi:
     * Menampilkan halaman bantuan dengan form untuk membuat tiket.
     *
     * Alur Proses:
     * 1. Ambil semua kategori.
     * 2. Cek status live service.
     * 3. Generate captcha sederhana.
     * 4. Simpan captcha ke session.
     * 5. Kembalikan view form tiket.
     *
     * Query yang Digunakan:
     * - Category::all(): Ambil semua kategori
     * - Setting::bool(): Cek status live service
     *
     * Output:
     * - View 'guest.help' dengan data kategori dan captcha.
     */
    public function create()
    {
        $categories = Category::all();
        $liveServiceEnabled = Setting::bool('live_service_enabled', true);

        // Generate simple captcha
        $captcha = rand(1000, 9999);
        session(['captcha' => $captcha]);
        return view('guest.help', compact('categories', 'captcha', 'liveServiceEnabled'));
    }

    /**
     * =========================================================================
     * 2. METODE STORE - SIMPAN TIKET BARU
     * =========================================================================
     *
     * Fungsi:
     * Membuat tiket baru berdasarkan laporan pengguna.
     *
     * Alur Proses:
     * 1. Validasi input form.
     * 2. Periksa batas permintaan berdasarkan IP dan email.
     * 3. Cek captcha untuk non-JSON request.
     * 4. Simpan tiket dan log dalam transaksi.
     * 5. Auto-assign tiket ke staff yang tersedia.
     * 6. Simpan ticket ID ke session.
     * 7. Kembalikan response JSON atau redirect.
     *
     * Query yang Digunakan:
     * - Ticket::create(): Insert tiket baru
     * - TicketLog::create(): Insert log aktivitas
     * - $this->assignTicketToAvailableStaff(): Auto-assign staff
     *
     * Output:
     * - RedirectResponse atau JsonResponse dengan data tiket.
     */
    public function store(Request $request)
    {
        // ✅ Validasi
        $validationRules = [
            'name' => 'required|string|max:50',
            'email' => 'required|email|max:50',
            'subject' => 'required|string|max:200',
            'message' => 'required|string',
            'category_id' => 'required|exists:categories,id',
        ];

        // Only require captcha for non-JSON requests
        if (!$request->expectsJson()) {
            $validationRules['captcha'] = 'required|string';
        }

        $request->validate($validationRules);

        // Anti-spam checks
        $ip = $request->ip();
        $email = $request->email;

        // Check IP rate limit
        if (Cache::has("ticket_ip_{$ip}")) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Terlalu banyak permintaan dari IP ini. Coba lagi dalam 1 menit.'], 429);
            }
            return redirect()->back()->withErrors(['error' => 'Terlalu banyak permintaan dari IP ini. Coba lagi dalam 1 menit.']);
        }

        // Check email rate limit
        if (Cache::has("ticket_email_{$email}")) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Email ini sudah digunakan baru-baru ini. Coba lagi dalam 1 menit.'], 429);
            }
            return redirect()->back()->withErrors(['error' => 'Email ini sudah digunakan baru-baru ini. Coba lagi dalam 1 menit.']);
        }

        // Captcha check only for non-JSON requests
        if (!$request->expectsJson() && $request->captcha != session('captcha')) {
            return redirect()->back()->withErrors(['captcha' => 'Captcha salah.']);
        }

        // Set cache for 1 minute
        Cache::put("ticket_ip_{$ip}", true, 60);
        Cache::put("ticket_email_{$email}", true, 60);

        // ✅ Buat tiket + auto assign staff dalam transaksi
        $ticket = DB::transaction(function () use ($request) {
            $ticket = Ticket::create([
                'name' => $request->name,
                'email' => $request->email,
                'subject' => $request->subject,
                'message' => $request->message,
                'category_id' => $request->category_id,
                'status' => 'open',
            ]);

            TicketLog::create([
                'ticket_id' => $ticket->id,
                'action' => 'created',
                'description' => 'Tiket dibuat oleh user',
            ]);

            $staffProfile = $this->assignTicketToAvailableStaff($ticket);

            if (!$staffProfile) {
                $ticket->update(['status' => 'waiting']);
                TicketLog::create([
                    'ticket_id' => $ticket->id,
                    'action' => 'waiting',
                    'description' => 'Belum ada staff tersedia',
                ]);
            }

            return $ticket;
        });

        // Store ticket ID in session for persistence
        session()->push('my_tickets', $ticket->id);
        session(['ticket_id' => $ticket->id]);
        
        // For guest users, store in separate session keys for chat widget
        if (!Auth::check()) {
            session(['guest_ticket_id' => $ticket->id]);
            if ($request->email) {
                session(['guest_email' => $request->email]);
            }
        }
        
        session()->save(); // Explicitly save session before response

        // Return JSON if requested as API
        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Tiket berhasil dibuat!',
                'ticket_id' => $ticket->id,
                'ticket' => $ticket
            ], 201);
        }

        return redirect()->back()->with('success', 'Tiket berhasil dibuat!')->with('ticket_id', $ticket->id);
    }

    /**
     * =========================================================================
     * 3. METODE STORE REPORT - SIMPAN LAPORAN ARTIKEL
     * =========================================================================
     *
     * Fungsi:
     * Membuat tiket laporan dari halaman artikel dengan status waiting.
     *
     * Alur Proses:
     * 1. Validasi input laporan.
     * 2. Periksa batas permintaan IP dan email.
     * 3. Simpan tiket sebagai status waiting.
     * 4. Auto-assign ke staff dengan waiting tickets paling sedikit.
     * 5. Simpan ticket ID ke session.
     * 6. Kembalikan response JSON atau redirect.
     *
     * Query yang Digunakan:
     * - Ticket::create(): Insert tiket laporan
     * - TicketLog::create(): Insert log aktivitas
     * - $this->assignReportToStaff(): Auto-assign staff
     *
     * Output:
     * - RedirectResponse atau JsonResponse dengan data tiket.
     */
    public function storeReport(Request $request)
    {
        // ✅ Validasi
        $validationRules = [
            'name' => 'required|string|max:50',
            'email' => 'required|email|max:50',
            'subject' => 'required|string|max:200',
            'message' => 'required|string',
            'category_id' => 'required|exists:categories,id',
        ];

        $request->validate($validationRules);

        // Anti-spam checks
        $ip = $request->ip();
        $email = $request->email;

        // Check IP rate limit
        if (Cache::has("report_ip_{$ip}")) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Terlalu banyak permintaan dari IP ini. Coba lagi dalam 1 menit.'], 429);
            }
            return redirect()->back()->withErrors(['error' => 'Terlalu banyak permintaan dari IP ini. Coba lagi dalam 1 menit.']);
        }

        // Check email rate limit
        if (Cache::has("report_email_{$email}")) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Email ini sudah digunakan baru-baru ini. Coba lagi dalam 1 menit.'], 429);
            }
            return redirect()->back()->withErrors(['error' => 'Email ini sudah digunakan baru-baru ini. Coba lagi dalam 1 menit.']);
        }

        // Set cache for 1 minute
        Cache::put("report_ip_{$ip}", true, 60);
        Cache::put("report_email_{$email}", true, 60);

        // ✅ Buat laporan sebagai tiket waiting yang ditangguhkan ke staf
        $ticket = DB::transaction(function () use ($request) {
            $ticket = Ticket::create([
                'name' => $request->name,
                'email' => $request->email,
                'subject' => $request->subject,
                'message' => $request->message,
                'category_id' => $request->category_id,
                'status' => 'waiting',
            ]);

            TicketLog::create([
                'ticket_id' => $ticket->id,
                'action' => 'created',
                'description' => 'Laporan dibuat dari halaman artikel',
            ]);

            $assignedReportStaff = $this->assignReportToStaff($ticket);

            if (!$assignedReportStaff) {
                TicketLog::create([
                    'ticket_id' => $ticket->id,
                    'action' => 'waiting',
                    'description' => 'Belum ada staff tersedia untuk menangani laporan ini',
                ]);
            }

            return $ticket;
        });

        // Store ticket ID in session for persistence
        session()->push('my_tickets', $ticket->id);
        session(['ticket_id' => $ticket->id]);
        
        // For guest users, store in separate session keys for chat widget
        if (!Auth::check()) {
            session(['guest_ticket_id' => $ticket->id]);
            if ($request->email) {
                session(['guest_email' => $request->email]);
            }
        }
        
        session()->save();

        // Return JSON if requested as API
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil dibuat!',
                'ticket_id' => $ticket->id,
                'ticket' => $ticket
            ], 201);
        }

        return redirect()->back()->with('success', 'Laporan berhasil dibuat!')->with('ticket_id', $ticket->id);
    }

    /**
     * =========================================================================
     * 4. Metode Meminta OTP
     * =========================================================================
     * 
     * Metode ini membuat OTP untuk verifikasi pembuatan tiket livechat atau report.
     * 
     * Parameter:
     * Request $request
     * 
     * Return:
     * JsonResponse
     */
    public function requestOtp(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'email' => 'required|email|max:50',
            'subject' => 'required|string|max:200',
            'message' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'type' => 'required|in:livechat,report',
        ]);

        \Log::info('OTP Request received', ['email' => $validated['email'], 'type' => $validated['type']]);

        $ip = $request->ip();
        $email = $validated['email'];

        if (Cache::has("ticket_otp_ip_{$ip}")) {
            \Log::warning('IP rate limit hit', ['ip' => $ip]);
            return response()->json(['success' => false, 'message' => 'Terlalu banyak permintaan dari IP ini. Coba lagi dalam 1 menit.'], 429);
        }

        if (Cache::has("ticket_otp_email_{$email}")) {
            \Log::warning('Email rate limit hit', ['email' => $email]);
            return response()->json(['success' => false, 'message' => 'Email ini sudah digunakan baru-baru ini. Coba lagi dalam 1 menit.'], 429);
        }

        Cache::put("ticket_otp_ip_{$ip}", true, 60);
        Cache::put("ticket_otp_email_{$email}", true, 60);

        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if ($validated['type'] === 'livechat' && !Setting::bool('live_service_enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Live service sedang offline. Silakan buat laporan/report atau coba lagi nanti.',
            ], 423);
        }

        $otp = TicketOtp::create([
            'name' => $validated['name'],
            'email' => $email,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'category_id' => $validated['category_id'],
            'type' => $validated['type'],
            'otp_code' => $otpCode,
            'expires_at' => now()->addMinutes(15),
            'token' => Str::random(60),
        ]);

        \Log::info('OTP Created', ['otp_id' => $otp->id, 'otp_code' => $otpCode, 'email' => $email]);

        try {
            Mail::to($email)->send(new TicketOtpMail($otpCode, $validated['type']));
            \Log::info('OTP Email sent successfully', ['email' => $email, 'otp_code' => $otpCode]);
        } catch (\Exception $e) {
            \Log::error('Failed to send OTP email', ['email' => $email, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal mengirim OTP. Silakan coba lagi.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kode OTP telah dikirim ke email Anda.',
            'verification_token' => $otp->token,
        ]);
    }

    /**
     * =========================================================================
     * 5. Metode Memverifikasi OTP
     * =========================================================================
     * 
     * Metode ini memverifikasi kode OTP dan membuat tiket setelah validasi.
     * 
     * Parameter:
     * Request $request
     * 
     * Return:
     * JsonResponse
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'verification_token' => 'required|string',
            'otp_code' => 'required|string|size:6',
        ]);

        $result = null;
        $ticket = DB::transaction(function () use ($request, &$result) {
            $otp = TicketOtp::where('token', $request->verification_token)->lockForUpdate()->first();

            if (!$otp) {
                $result = ['success' => false, 'code' => 404, 'message' => 'Token verifikasi tidak valid.'];
                return null;
            }

            if ($otp->expires_at->isPast()) {
                $otp->delete();
                $result = ['success' => false, 'code' => 422, 'message' => 'Kode OTP telah kedaluwarsa. Silakan minta ulang.'];
                return null;
            }

            if ($otp->attempts >= 3) {
                $otp->delete();
                $result = ['success' => false, 'code' => 422, 'message' => 'Anda telah melebihi batas percobaan OTP. Silakan minta ulang.'];
                return null;
            }

            if ($otp->otp_code !== $request->otp_code) {
                $otp->increment('attempts');
                $remaining = max(0, 3 - $otp->attempts);

                if ($otp->attempts >= 3) {
                    $otp->delete();
                    $result = ['success' => false, 'code' => 422, 'message' => 'Anda sudah salah 3 kali. Silakan lakukan permintaan ulang.'];
                    return null;
                }

                $result = ['success' => false, 'code' => 422, 'message' => "OTP salah. Kesempatan tersisa: {$remaining}."];
                return null;
            }

            if ($otp->type === 'livechat' && !Setting::bool('live_service_enabled', true)) {
                $otp->delete();
                $result = ['success' => false, 'code' => 423, 'message' => 'Live service sedang offline. Silakan buat laporan/report.'];
                return null;
            }

            $ticket = Ticket::create([
                'name' => $otp->name,
                'email' => $otp->email,
                'subject' => $otp->subject,
                'message' => $otp->message,
                'category_id' => $otp->category_id,
                'status' => $otp->type === 'report' ? 'waiting' : 'open',
                'priority' => 'low',
                'tracking_token' => Str::random(60),
                'email_verified_at' => now(),
            ]);

            TicketLog::create([
                'ticket_id' => $ticket->id,
                'action' => 'created',
                'description' => 'Tiket dibuat setelah verifikasi OTP.',
            ]);

            if ($otp->type === 'livechat') {
                $staffProfile = $this->assignTicketToAvailableStaff($ticket);

                if (!$staffProfile) {
                    $ticket->update(['status' => 'waiting']);
                    TicketLog::create([
                        'ticket_id' => $ticket->id,
                        'action' => 'waiting',
                        'description' => 'Belum ada staff tersedia setelah verifikasi OTP.',
                    ]);
                }
            } else {
                $assignedReportStaff = $this->assignReportToStaff($ticket);

                if (!$assignedReportStaff) {
                    TicketLog::create([
                        'ticket_id' => $ticket->id,
                        'action' => 'waiting',
                        'description' => 'Belum ada staff tersedia untuk laporan setelah verifikasi OTP.',
                    ]);
                }
            }

            $ticketType = $otp->type; // capture before delete
            $otp->delete();
            $result = ['success' => true, 'ticket' => $ticket, 'ticket_type' => $ticketType];
            return $ticket;
        });

        if (!$result || !$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message'] ?? 'Token verifikasi tidak valid.'], $result['code'] ?? 422);
        }

        $trackingUrl = route('tickets.track', ['token' => $ticket->tracking_token]);
        Mail::to($ticket->email)->send(new TicketTrackingMail($ticket, $trackingUrl));

        return response()->json([
            'success'       => true,
            'message'       => 'OTP berhasil diverifikasi. Tiket Anda telah dibuat.',
            'ticket_id'     => $ticket->id,
            'tracking_url'  => $trackingUrl,
            'ticket_status' => $ticket->status,          // 'assigned' | 'waiting'
            'ticket_type'   => $result['ticket_type'],   // 'livechat' | 'report'
        ]);
    }

    /**
     * =========================================================================
     * 6. Metode Pelacakan Tiket
     * =========================================================================
     * 
     * Metode ini menampilkan halaman pelacakan tiket berdasarkan token.
     * 
     * Parameter:
     * string $token
     * 
     * Return:
     * View
     */
    public function track(string $token)
    {
        $ticket = Ticket::with(['category', 'messages', 'logs'])->where('tracking_token', $token)->firstOrFail();

        return view('guest.tickets.track', compact('ticket'));
    }

    /**
     * =========================================================================
     * 7. METODE ASSIGN TICKET TO AVAILABLE STAFF - ASSIGN TIKET KE STAFF
     * =========================================================================
     *
     * Fungsi:
     * Menugaskan tiket ke staff yang tersedia dengan load balancing.
     *
     * Alur Proses:
     * 1. Query staff profiles yang tersedia di kategori tiket.
     * 2. Hitung active tickets dan waiting reports per staff.
     * 3. Sort staff berdasarkan beban kerja terendah.
     * 4. Assign tiket ke staff terbaik.
     * 5. Update status staff menjadi busy.
     * 6. Catat log penugasan.
     *
     * Query yang Digunakan:
     * - StaffProfile::where()->with()->lockForUpdate(): Cari staff tersedia
     * - $profile->user->tickets()->count(): Hitung tiket staff
     * - $ticket->update(): Update tiket
     * - $bestStaff->update(): Update status staff
     *
     * Output:
     * - StaffProfile atau null jika tidak ada staff tersedia.
     */
    private function assignTicketToAvailableStaff(Ticket $ticket): ?StaffProfile
    {
        return DB::transaction(function () use ($ticket) {
            $staffProfiles = StaffProfile::where('category_id', $ticket->category_id)
                ->where('is_busy', false)
                ->with('user')
                ->lockForUpdate()
                ->get();

            if ($staffProfiles->isEmpty()) {
                return null;
            }

            $staffWithCounts = $staffProfiles->map(function ($profile) {
                return [
                    'profile' => $profile,
                    'active_tickets' => $profile->user->tickets()
                        ->whereIn('status', ['assigned', 'progress'])
                        ->count(),
                    'waiting_reports' => $profile->user->tickets()
                        ->where('status', 'waiting')
                        ->count(),
                ];
            });

            $best = $staffWithCounts->sort(function ($a, $b) {
                if ($a['active_tickets'] !== $b['active_tickets']) {
                    return $a['active_tickets'] <=> $b['active_tickets'];
                }
                if ($a['waiting_reports'] !== $b['waiting_reports']) {
                    return $a['waiting_reports'] <=> $b['waiting_reports'];
                }
                return $a['profile']->id <=> $b['profile']->id;
            })->first();

            if (!$best) {
                return null;
            }

            $bestStaff = $best['profile'];

            $ticket->update([
                'staff_id' => $bestStaff->user_id,
                'status' => 'assigned',
                'assigned_at' => now(),
            ]);

            $bestStaff->update(['is_busy' => true]);

            TicketLog::create([
                'ticket_id' => $ticket->id,
                'action' => 'assigned',
                'description' => 'Tiket di-assign ke staff: ' . $bestStaff->user->name .
                    ' (active: ' . $bestStaff->user->tickets()->whereIn('status', ['assigned', 'progress'])->count() .
                    ', waiting: ' . $bestStaff->user->tickets()->where('status', 'waiting')->count() . ')',
            ]);

            return $bestStaff;
        });
    }

    /**
     * =========================================================================
     * 8. METODE ASSIGN REPORT TO STAFF - ASSIGN LAPORAN KE STAFF
     * =========================================================================
     *
     * Fungsi:
     * Menugaskan laporan ke staff sebagai tiket waiting tanpa live chat.
     *
     * Alur Proses:
     * 1. Query staff profiles di kategori tiket.
     * 2. Hitung waiting tickets per staff.
     * 3. Sort staff berdasarkan beban waiting terendah.
     * 4. Assign tiket ke staff terbaik.
     * 5. Catat log penugasan.
     *
     * Query yang Digunakan:
     * - StaffProfile::where()->with()->get(): Cari staff
     * - $profile->user->tickets()->count(): Hitung waiting tickets
     * - $ticket->update(): Update tiket
     *
     * Output:
     * - StaffProfile atau null jika tidak ada staff tersedia.
     */
    private function assignReportToStaff(Ticket $ticket): ?StaffProfile
    {
        $staffProfiles = StaffProfile::where('category_id', $ticket->category_id)
            ->with('user')
            ->get();

        if ($staffProfiles->isEmpty()) {
            return null;
        }

        $staffWithWaitingCounts = $staffProfiles->map(function ($profile) {
            return [
                'profile' => $profile,
                'waiting_count' => $profile->user->tickets()
                    ->where('status', 'waiting')
                    ->count(),
            ];
        });

        $best = $staffWithWaitingCounts->sort(function ($a, $b) {
            if ($a['waiting_count'] !== $b['waiting_count']) {
                return $a['waiting_count'] <=> $b['waiting_count'];
            }
            return $a['profile']->id <=> $b['profile']->id;
        })->first();

        if (!$best) {
            return null;
        }

        $bestStaff = $best['profile'];

        $ticket->update([
            'staff_id' => $bestStaff->user_id,
            'assigned_at' => now(),
            'status' => 'waiting',
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'action' => 'assigned',
            'description' => 'Laporan ditugaskan ke staf: ' . $bestStaff->user->name .
                ' (waiting load: ' . $best['waiting_count'] . ')',
        ]);

        return $bestStaff;
    }

    /**
     * =========================================================================
     * 9. METODE INDEX - DAFTAR TIKET ADMIN
     * =========================================================================
     *
     * Fungsi:
     * Menampilkan daftar tiket untuk admin/staff.
     *
     * Alur Proses:
     * 1. Query semua tiket dengan relasi kategori dan staff.
     * 2. Pagination 20 item per halaman.
     * 3. Kembalikan view daftar tiket.
     *
     * Query yang Digunakan:
     * - Ticket::with()->latest()->paginate(): Ambil tiket dengan relasi
     *
     * Output:
     * - View 'tickets.index' dengan data tiket.
     */
    public function index()
    {
        $tickets = Ticket::with(['category', 'staff'])->latest()->paginate(20);

        return view('tickets.index', compact('tickets'));
    }

    /**
     * =========================================================================
     * 10. METODE SHOW - DETAIL TIKET
     * =========================================================================
     *
     * Fungsi:
     * Menampilkan detail tiket beserta pesan dan log terkait.
     *
     * Alur Proses:
     * 1. Query tiket dengan relasi kategori, staff, pesan, dan log.
     * 2. Kembalikan view detail tiket.
     *
     * Query yang Digunakan:
     * - Ticket::with()->findOrFail(): Ambil tiket dengan relasi
     *
     * Output:
     * - View 'tickets.show' dengan data tiket.
     */
    public function show($id)
    {
        $ticket = Ticket::with(['category', 'staff', 'messages', 'logs'])->findOrFail($id);

        return view('tickets.show', compact('ticket'));
    }

    /**
     * =========================================================================
     * 11. METODE UPDATE STATUS - PERBARUI STATUS TIKET
     * =========================================================================
     *
     * Fungsi:
     * Memperbarui status tiket oleh staff.
     *
     * Alur Proses:
     * 1. Cek apakah staff adalah pemilik tiket.
     * 2. Validasi input status.
     * 3. Update status tiket.
     * 4. Jika status closed, update closed_at dan set staff tidak busy.
     * 5. Catat log perubahan status.
     *
     * Query yang Digunakan:
     * - $ticket->update(): Update status tiket
     * - StaffProfile::where()->update(): Update status staff
     * - TicketLog::create(): Buat log aktivitas
     *
     * Output:
     * - Redirect back dengan pesan sukses.
     */
    public function updateStatus(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);

        // Cek apakah staff yang update adalah pemilik tiket
        if ($ticket->staff_id !== auth()->id()) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah tiket ini.');
        }

        $request->validate([
            'status' => 'required|in:assigned,progress,closed',
        ]);

        $ticket->update([
            'status' => $request->status,
        ]);

        // Kalau selesai → staff jadi tidak sibuk
        if ($request->status === 'closed') {
            $ticket->update([
                'closed_at' => now()
            ]);

            $staffProfile = StaffProfile::where('user_id', $ticket->staff_id)->first();

            if ($staffProfile) {
                $staffProfile->update([
                    'is_busy' => false
                ]);
            }
        }

        // Log
        TicketLog::create([
            'ticket_id' => $ticket->id,
            'action' => $request->status,
            'description' => 'Status diubah menjadi ' . $request->status,
        ]);

        return back()->with('success', 'Status berhasil diupdate');
    }
}
