<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\StaffProfile;
use App\Models\TicketLog;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

/**
 * =========================================================================
 * STAFF TICKET CONTROLLER - PENGELOLAAN TIKET STAFF
 * =========================================================================
 *
 * Controller ini bertanggung jawab untuk mengelola tiket yang ditugaskan
 * kepada staff helpdesk. Staff dapat melihat, memproses, menyelesaikan,
 * menolak, dan menangguhkan tiket.
 *
 * Fitur Utama:
 * - Daftar tiket yang ditugaskan ke staff
 * - Detail tiket dengan pesan dan log
 * - Update priority tiket
 * - Mulai mengerjakan tiket (progress)
 * - Menolak tiket
 * - Menyelesaikan tiket
 * - Menangguhkan tiket
 * - Reassign tiket ke staff lain
 * - Menambahkan log manual
 *
 * Model Terkait:
 * - Ticket: Model tiket
 * - StaffProfile: Profil staff
 * - TicketLog: Log aktivitas tiket
 */
class TicketController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE INDEX - DAFTAR TIKET STAFF
     * =========================================================================
     *
     * Fungsi:
     * Menampilkan daftar tiket yang ditugaskan ke staff yang sedang login.
     *
     * Alur Proses:
     * 1. Mengambil data user yang sedang login.
     * 2. Query tiket yang ditugaskan ke staff ini.
     * 3. Filter berdasarkan priority jika ada.
     * 4. Pisahkan tiket berdasarkan status (active, completed, waiting).
     * 5. Mengembalikan view dengan data tiket.
     *
     * Query yang Digunakan:
     * - Ticket::where('staff_id', $user->id): Filter tiket milik staff
     * - where('priority', $request->priority): Filter priority
     * - whereIn('status', ['assigned', 'progress']): Tiket aktif
     * - where('status', 'closed'): Tiket selesai
     * - where('status', 'waiting'): Tiket menunggu
     *
     * Output:
     * - View 'staff.tickets.index' dengan data tiket.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        // Get tiket yang ditugaskan ke staff ini
        $ticketsQuery = Ticket::where('staff_id', $user->id)
            ->with(['category', 'user']);

        // Filter by priority if provided
        if ($request->has('priority') && $request->priority) {
            $ticketsQuery->where('priority', $request->priority);
        }

        $tickets = $ticketsQuery->latest()->get();

        // Pisahkan berdasarkan status
        $activeTicket = Ticket::where('staff_id', $user->id)
            ->whereIn('status', ['assigned', 'progress'])
            ->with(['category', 'user'])
            ->first();

        $completedTicketsQuery = Ticket::where('staff_id', $user->id)
            ->where('status', 'closed')
            ->with(['category', 'user']);

        // Filter by priority if provided
        if ($request->has('priority') && $request->priority) {
            $completedTicketsQuery->where('priority', $request->priority);
        }

        $completedTickets = $completedTicketsQuery->latest()->get();

        $waitingTicketsQuery = Ticket::where('staff_id', $user->id)
            ->where('status', 'waiting')
            ->with(['category', 'user']);

        // Filter by priority if provided
        if ($request->has('priority') && $request->priority) {
            $waitingTicketsQuery->where('priority', $request->priority);
        }

        $waitingTickets = $waitingTicketsQuery->oldest()->get();

        return view('staff.tickets.index', compact('user', 'tickets', 'activeTicket', 'completedTickets', 'waitingTickets'));
    }

    /**
     * =========================================================================
     * 2. METODE SHOW - DETAIL TIKET
     * =========================================================================
     *
     * Fungsi:
     * Menampilkan detail tiket beserta pesan dan log.
     *
     * Alur Proses:
     * 1. Cek apakah tiket milik staff yang sedang login.
     * 2. Load relasi kategori, user, pesan, dan log.
     * 3. Mengembalikan view detail tiket.
     *
     * Query yang Digunakan:
     * - $ticket->load(['category', 'user', 'messages.sender', 'logs']): Load relasi
     *
     * Output:
     * - View 'staff.tickets.show' dengan data tiket.
     */
    public function show(Ticket $ticket): View
    {
        // Pastikan staff hanya bisa lihat tiket mereka
        if ($ticket->staff_id !== auth()->id()) {
            abort(403, 'Anda tidak memiliki akses ke tiket ini');
        }

        $ticket->load(['category', 'user', 'messages.sender', 'logs']);

        return view('staff.tickets.show', compact('ticket'));
    }

    /**
     * =========================================================================
     * 3. METODE UPDATE PRIORITY - UPDATE PRIORITY TIKET
     * =========================================================================
     *
     * Fungsi:
     * Memperbarui priority tiket.
     *
     * Alur Proses:
     * 1. Cek otorisasi apakah tiket milik staff.
     * 2. Validasi input priority.
     * 3. Update priority tiket.
     * 4. Catat log perubahan priority.
     * 5. Redirect kembali dengan pesan sukses.
     *
     * Query yang Digunakan:
     * - $ticket->update(['priority' => $request->priority]): Update priority
     * - TicketLog::create(): Buat log perubahan
     *
     * Output:
     * - Redirect back dengan pesan sukses.
     */
    public function updatePriority(Request $request, Ticket $ticket): RedirectResponse
    {
        // Validasi akses
        if ($ticket->staff_id !== auth()->id()) {
            abort(403, 'Anda tidak memiliki akses');
        }

        $request->validate([
            'priority' => 'required|in:low,medium,high',
        ]);

        $oldPriority = $ticket->priority;
        $ticket->update([
            'priority' => $request->priority,
        ]);

        // Log perubahan
        TicketLog::create([
            'ticket_id' => $ticket->id,
            'action' => 'priority_updated',
            'description' => "Priority diubah dari {$oldPriority} menjadi {$request->priority}",
        ]);

        return back()->with('success', 'Priority tiket diperbarui');
    }

    /**
     * =========================================================================
     * 4. METODE START PROGRESS - MULAI MENGERJAKAN TIKET
     * =========================================================================
     *
     * Fungsi:
     * Mengubah status tiket menjadi progress dan meng-assign ke staff.
     *
     * Alur Proses:
     * 1. Jika tiket waiting dan belum di-assign, assign ke staff.
     * 2. Update status staff menjadi busy.
     * 3. Catat log bahwa tiket di-claim.
     * 4. Broadcast event StaffConnected.
     * 5. Jika tiket sudah di-assign, hanya update status.
     *
     * Query yang Digunakan:
     * - $ticket->update(): Update status tiket
     * - StaffProfile::where()->update(): Update status staff
     * - TicketLog::create(): Buat log aktivitas
     *
     * Output:
     * - Redirect back dengan pesan sukses.
     */
    public function startProgress(Ticket $ticket): RedirectResponse
    {
        $user = auth()->user();

        if ($ticket->status === 'waiting' && $ticket->staff_id === null) {
            $ticket->update([
                'staff_id' => $user->id,
                'status' => 'progress',
                'assigned_at' => now(),
            ]);

            StaffProfile::where('user_id', $user->id)->update([
                'is_busy' => true,
            ]);

            TicketLog::create([
                'ticket_id' => $ticket->id,
                'action' => 'claimed',
                'description' => 'Tiket di-claim dan dimulai oleh staff: ' . $user->name,
            ]);

            broadcast(new \App\Events\StaffConnected($ticket, $user))->toOthers();

            return back()->with('success', 'Tiket berhasil di-claim dan mulai dikerjakan');
        }

        if ($ticket->staff_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses');
        }

        $ticket->update([
            'status' => 'progress',
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'action' => 'progress_started',
            'description' => 'Staff mulai mengerjakan tiket',
        ]);

        broadcast(new \App\Events\StaffConnected($ticket, $user))->toOthers();

        return back()->with('success', 'Mulai mengerjakan tiket');
    }

    /**
     * =========================================================================
     * 5. METODE REJECT - TOLAK TIKET
     * =========================================================================
     *
     * Fungsi:
     * Menolak tiket yang ditugaskan ke staff.
     *
     * Alur Proses:
     * 1. Cek otorisasi apakah tiket milik staff.
     * 2. Cek apakah status tiket assigned atau waiting.
     * 3. Update status tiket menjadi closed.
     * 4. Update status staff menjadi tidak busy.
     * 5. Catat log penolakan.
     * 6. Kirim email penolakan ke guest/customer.
     * 7. Broadcast event TicketClosed.
     *
     * Query yang Digunakan:
     * - $ticket->update(): Update status tiket
     * - StaffProfile::where()->update(): Update status staff
     * - TicketLog::create(): Buat log penolakan
     *
     * Output:
     * - Redirect ke route('staff.tickets.index') dengan pesan sukses.
     */
    public function reject(Ticket $ticket): RedirectResponse
    {
        $user = auth()->user();

        // Validasi akses
        if ($ticket->staff_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses');
        }

        // Hanya izinkan penolakan untuk tiket assigned atau waiting
        if (!in_array($ticket->status, ['assigned', 'waiting'])) {
            return back()->withErrors(['error' => 'Tiket hanya dapat ditolak saat status assigned atau waiting.']);
        }

        // Update ticket status to closed
        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        // Update staff status jadi tidak sibuk
        StaffProfile::where('user_id', $user->id)->update([
            'is_busy' => false,
        ]);

        // Log penolakan
        TicketLog::create([
            'ticket_id' => $ticket->id,
            'action' => 'rejected',
            'description' => 'Tiket ditolak oleh staff: ' . $user->name . '. Staff tidak dapat menerima tiket pada saat ini.',
        ]);

        // Send rejection email to guest/customer
        try {
            \Mail::to($ticket->email)->send(new \App\Mail\TicketRejectionMail($ticket));
        } catch (\Exception $e) {
            \Log::error('Failed to send rejection email: ' . $e->getMessage());
        }

        broadcast(new \App\Events\TicketClosed($ticket));

        return redirect()->route('staff.tickets.index')->with('success', 'Tiket berhasil ditolak. Guest telah menerima notifikasi.');
    }

    /**
     * =========================================================================
     * 6. METODE COMPLETE - TANDAI TIKET SELESAI
     * =========================================================================
     *
     * Fungsi:
     * Menandai tiket sebagai selesai dan mencari tiket waiting berikutnya.
     *
     * Alur Proses:
     * 1. Cek otorisasi apakah tiket milik staff.
     * 2. Validasi status tiket (progress atau waiting).
     * 3. Validasi input priority.
     * 4. Update status tiket menjadi closed.
     * 5. Update status staff menjadi tidak busy.
     * 6. Catat log penyelesaian.
     * 7. Cari tiket waiting dengan kategori yang sama.
     * 8. Assign tiket waiting ke staff yang paling available.
     * 9. Broadcast event TicketClosed.
     *
     * Query yang Digunakan:
     * - $ticket->update(): Update status tiket
     * - StaffProfile::where()->update(): Update status staff
     * - TicketLog::create(): Buat log penyelesaian
     * - Ticket::where()->oldest()->first(): Cari tiket waiting
     *
     * Output:
     * - Redirect ke route('staff.tickets.index') dengan pesan sukses.
     */
    public function complete(Request $request, Ticket $ticket): RedirectResponse
    {
        // Validasi akses
        if ($ticket->staff_id !== auth()->id()) {
            abort(403, 'Anda tidak memiliki akses');
        }

        // Hanya izinkan penyelesaian untuk tiket progress atau waiting
        if (! in_array($ticket->status, ['progress', 'waiting'])) {
            return back()->withErrors(['error' => 'Tiket hanya dapat ditandai selesai saat status progress atau waiting.']);
        }

        $request->validate([
            'priority' => 'required|in:low,medium,high',
        ]);

        $user = auth()->user();

        // Update ticket
        $ticket->update([
            'status' => 'closed',
            'priority' => $request->priority,
            'closed_at' => now(),
        ]);

        // Update staff status jadi tidak sibuk
        StaffProfile::where('user_id', $user->id)->update([
            'is_busy' => false,
        ]);

        // Log completion
        TicketLog::create([
            'ticket_id' => $ticket->id,
            'action' => 'closed',
            'description' => 'Tiket diselesaikan oleh staff: ' . $user->name,
        ]);

        // ✨ Cari tiket waiting dengan kategori yang sama untuk di-assign
        $staffProfile = StaffProfile::where('user_id', $user->id)->first();
        
        if ($staffProfile) {
            $nextTicket = Ticket::where('category_id', $staffProfile->category_id)
                ->where('status', 'waiting')
                ->whereNull('staff_id') // Hanya tiket yang belum di-assign
                ->oldest()
                ->first();

            if ($nextTicket) {
                // Cari staff paling available (tidak ada active tickets) di kategori yang sama
                $availableStaff = StaffProfile::where('category_id', $staffProfile->category_id)
                    ->where('is_busy', false)
                    ->get()
                    ->sortBy(function ($profile) {
                        // Prioritas: staff tanpa tiket active sama sekali
                        $activeCount = $profile->user->tickets()
                            ->whereIn('status', ['assigned', 'progress', 'waiting'])
                            ->count();
                        return $activeCount;
                    })
                    ->first();

                if ($availableStaff) {
                    // Assign ke staff yang paling available
                    $nextTicket->update([
                        'staff_id' => $availableStaff->user_id,
                        'status' => 'assigned',
                        'assigned_at' => now(),
                    ]);

                    // Update status staff jadi sibuk
                    $availableStaff->update([
                        'is_busy' => true,
                    ]);

                    TicketLog::create([
                        'ticket_id' => $nextTicket->id,
                        'action' => 'assigned',
                        'description' => 'Tiket di-assign ke staff: ' . $availableStaff->user->name,
                    ]);
                }
            }
        }

        broadcast(new \App\Events\TicketClosed($ticket));

        return redirect()->route('staff.tickets.index')->with('success', 'Tiket berhasil ditandai selesai!');
    }

    /**
     * =========================================================================
     * 7. METODE SUSPEND - TANGGUHKAN TIKET
     * =========================================================================
     *
     * Fungsi:
     * Menangguhkan tiket sementara dan mencari tiket waiting berikutnya.
     *
     * Alur Proses:
     * 1. Cek otorisasi apakah tiket milik staff.
     * 2. Cek apakah tiket sudah closed.
     * 3. Update status tiket menjadi waiting.
     * 4. Update status staff menjadi tidak busy.
     * 5. Catat log penangguhan.
     * 6. Cari tiket waiting dengan kategori yang sama.
     * 7. Assign tiket waiting ke staff yang paling available.
     * 8. Broadcast event TicketClosed.
     *
     * Query yang Digunakan:
     * - $ticket->update(): Update status tiket
     * - StaffProfile::where()->update(): Update status staff
     * - TicketLog::create(): Buat log penangguhan
     * - Ticket::where()->oldest()->first(): Cari tiket waiting
     *
     * Output:
     * - Redirect back dengan pesan sukses.
     */
    public function suspend(Ticket $ticket): RedirectResponse
    {
        if ($ticket->staff_id !== auth()->id()) {
            abort(403, 'Anda tidak memiliki akses untuk menangguhkan tiket ini.');
        }

        if ($ticket->status === 'closed') {
            return back()->withErrors(['error' => 'Tiket sudah ditutup, tidak dapat ditangguhkan.']);
        }

        $ticket->update([
            'status' => 'waiting',
        ]);

        // Staff kembali tersedia untuk tiket lain
        $user = auth()->user();
        StaffProfile::where('user_id', auth()->id())->update([
            'is_busy' => false,
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'action' => 'waiting',
            'description' => 'Tiket ditangguhkan oleh staff dan chat dihentikan sementara.',
        ]);

        // ✨ Cari tiket waiting dengan kategori yang sama untuk di-assign
        $staffProfile = StaffProfile::where('user_id', $user->id)->first();
        
        if ($staffProfile) {
            $nextTicket = Ticket::where('category_id', $staffProfile->category_id)
                ->where('status', 'waiting')
                ->whereNull('staff_id') // Hanya tiket yang belum di-assign
                ->oldest()
                ->first();

            if ($nextTicket) {
                // Cari staff paling available (tidak ada active tickets) di kategori yang sama
                $availableStaff = StaffProfile::where('category_id', $staffProfile->category_id)
                    ->where('is_busy', false)
                    ->get()
                    ->sortBy(function ($profile) {
                        // Prioritas: staff tanpa tiket active sama sekali
                        $activeCount = $profile->user->tickets()
                            ->whereIn('status', ['assigned', 'progress', 'waiting'])
                            ->count();
                        return $activeCount;
                    })
                    ->first();

                if ($availableStaff) {
                    // Assign ke staff yang paling available
                    $nextTicket->update([
                        'staff_id' => $availableStaff->user_id,
                        'status' => 'assigned',
                        'assigned_at' => now(),
                    ]);

                    // Update status staff jadi sibuk
                    $availableStaff->update([
                        'is_busy' => true,
                    ]);

                    TicketLog::create([
                        'ticket_id' => $nextTicket->id,
                        'action' => 'assigned',
                        'description' => 'Tiket di-assign ke staff: ' . $availableStaff->user->name,
                    ]);
                }
            }
        }

        broadcast(new \App\Events\TicketClosed($ticket));

        return back()->with('success', 'Tiket berhasil ditangguhkan. Anda dapat melanjutkannya nanti.');
    }

    /**
     * =========================================================================
     * 8. METODE STORE LOG - TAMBAH CATATAN MANUAL
     * =========================================================================
     *
     * Fungsi:
     * Menambahkan catatan/log manual ke tiket.
     *
     * Alur Proses:
     * 1. Cek otorisasi apakah tiket milik staff.
     * 2. Validasi input description.
     * 3. Buat log baru dengan action 'staff_update'.
     * 4. Kembalikan response JSON atau redirect.
     *
     * Query yang Digunakan:
     * - TicketLog::create(): Buat log baru
     *
     * Output:
     * - JSON response atau redirect back dengan pesan sukses.
     */
    public function storeLog(Request $request, Ticket $ticket): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if ($ticket->staff_id !== auth()->id()) {
            abort(403, 'Anda tidak memiliki akses untuk menambahkan log tiket ini.');
        }

        $request->validate([
            'description' => 'required|string|max:2000',
        ]);

        $log = TicketLog::create([
            'ticket_id' => $ticket->id,
            'action' => 'staff_update',
            'description' => trim($request->description),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Log berhasil ditambahkan.',
                'log' => $log
            ]);
        }

        return back()->with('success', 'Log tiket berhasil ditambahkan.');
    }

    /**
     * =========================================================================
     * 9. METODE REASSIGN - REASSIGN TIKET KE STAFF LAIN
     * =========================================================================
     *
     * Fungsi:
     * Menugaskan ulang tiket ke staff lain dengan beban kerja paling sedikit.
     *
     * Alur Proses:
     * 1. Cek otorisasi apakah tiket milik staff.
     * 2. Cari staff baru dengan beban kerja paling sedikit di kategori sama.
     * 3. Update tiket dengan staff baru.
     * 4. Update status staff lama menjadi tidak busy.
     * 5. Update status staff baru menjadi busy.
     * 6. Catat log reassignment.
     *
     * Query yang Digunakan:
     * - StaffProfile::where()->with('user')->get(): Cari staff tersedia
     * - $ticket->update(): Update staff tiket
     * - StaffProfile::where()->update(): Update status staff
     * - TicketLog::create(): Buat log reassignment
     *
     * Output:
     * - Redirect back dengan pesan sukses.
     */
    public function reassign(Request $request, Ticket $ticket): RedirectResponse
    {
        // Validasi akses
        if ($ticket->staff_id !== auth()->id()) {
            abort(403, 'Anda tidak memiliki akses untuk reassign tiket ini.');
        }

        // Pilih staff baru dengan beban kerja paling sedikit di kategori sama
        $newStaffProfile = StaffProfile::where('category_id', $ticket->category_id)
            ->where('is_busy', false)
            ->where('user_id', '!=', $ticket->staff_id) // jangan assign ke diri sendiri
            ->with('user')
            ->get()
            ->sortBy(function ($profile) {
                return $profile->user->tickets()
                    ->whereIn('status', ['assigned', 'progress'])
                    ->count();
            })
            ->first();

        if (!$newStaffProfile) {
            return back()->withErrors(['error' => 'Tidak ada staff lain yang tersedia untuk kategori ini.']);
        }

        // Update ticket
        $oldStaff = $ticket->staff;
        $ticket->update([
            'staff_id' => $newStaffProfile->user_id,
            'assigned_at' => now(),
        ]);

        // Update busy status staff lama
        if ($oldStaff) {
            $oldStaffProfile = StaffProfile::where('user_id', $oldStaff->id)->first();
            if ($oldStaffProfile) {
                $oldStaffProfile->update(['is_busy' => false]);
            }
        }

        // Update busy status staff baru
        $newStaffProfile->update(['is_busy' => true]);

        // Log
        TicketLog::create([
            'ticket_id' => $ticket->id,
            'action' => 'reassigned',
            'description' => 'Tiket di-reassign dari ' . ($oldStaff ? $oldStaff->name : 'tidak ada') . ' ke ' . $newStaffProfile->user->name,
        ]);

        return back()->with('success', 'Tiket berhasil di-reassign ke ' . $newStaffProfile->user->name);
    }

    /**
     * =========================================================================
     * 10. METODE GET LOGS - AMBIL LOG TIKET
     * =========================================================================
     *
     * Fungsi:
     * Mengambil semua log tiket untuk ditampilkan di modal.
     *
     * Alur Proses:
     * 1. Cek otorisasi apakah tiket milik staff.
     * 2. Query semua log tiket dengan urutan terbaru.
     * 3. Map log ke format yang sesuai untuk response.
     * 4. Kembalikan response JSON.
     *
     * Query yang Digunakan:
     * - $ticket->logs()->latest()->get(): Ambil log tiket
     *
     * Output:
     * - JSON response dengan array log tiket.
     */
    public function getLogs(Ticket $ticket): JsonResponse
    {
        // Pastikan staff hanya bisa lihat logs tiket mereka
        if ($ticket->staff_id !== auth()->id()) {
            abort(403, 'Anda tidak memiliki akses ke tiket ini');
        }

        $logs = $ticket->logs()
            ->latest()
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'created_at' => $log->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'logs' => $logs,
        ]);
    }
}

