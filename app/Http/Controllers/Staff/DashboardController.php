<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\StaffProfile;
use App\Models\TicketLog;
use App\Services\TicketAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use App\Mail\TicketRejectionMail;
use App\Events\TicketClosed;
use Illuminate\View\View;

/**
 * =============================================================================
 * STAFF DASHBOARD CONTROLLER - DASHBOARD STAFF
 * =============================================================================
 * 
 * Controller ini menampilkan dashboard utama untuk staff helpdesk.
 * Dashboard ini menampilkan informasi tiket yang relevan dengan staff
 * dan artikel yang mereka buat.
 * 
 * Fitur Utama:
 * - Tampilan tiket hari ini yang ditugaskan ke staff
 * - Tampilan tiket waiting yang belum di-assign
 * - Statistik artikel yang dibuat staff
 * - Status live service
 * 
 * Model Terkait:
 * - Ticket: Data tiket
 * - Article: Artikel yang dibuat staff
 * - Setting: Konfigurasi sistem
 */
class DashboardController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE INDEX - TAMPILAN DASHBOARD STAFF
     * =========================================================================
     * 
     * Fungsi: Menampilkan dashboard staff dengan informasi terkait.
     * 
     * Alur Proses:
     * 1. Query tiket hari ini yang ditugaskan ke staff yang login
     * 2. Query tiket waiting yang belum di-assign ke staff manapun
     * 3. Hitung jumlah artikel yang dibuat staff
     * 4. Cek status live service
     * 5. Kembalikan view dashboard
     * 
     * Query yang Digunakan:
     * - Ticket::whereDate('created_at', today())->where('staff_id', auth()->id()):
     *   Tiket hari ini milik staff
     * - Ticket::where('status', 'waiting')->whereNull('staff_id'):
     *   Tiket waiting yang belum di-assign
     * - Article::where('staff_id', auth()->id())->count():
     *   Hitung artikel staff
     * - Setting::bool('live_service_enabled', true):
     *   Cek status live service
     * 
     * Output:
     * - View 'staff.dashboard' dengan data todayTickets, waitingTickets, 
     *   articleCount, liveServiceEnabled
     */
    public function index(): View
    {
        $todayTickets = Ticket::select(['id', 'name', 'email', 'subject', 'category_id', 'staff_id', 'status', 'priority', 'created_at'])
            ->whereDate('created_at', today())
            ->where('status', '!=', 'closed')
            ->where('staff_id', auth()->id())
            ->with([
                'category:id,name',
                'user:id,name,email'
            ])
            ->orderBy('created_at', 'asc')
            ->get();

        $waitingTickets = Ticket::select(['id', 'name', 'email', 'subject', 'category_id', 'status', 'priority', 'created_at'])
            ->where('status', 'waiting')
            ->whereNull('staff_id')
            ->with([
                'category:id,name',
                'user:id,name,email'
            ])
            ->orderBy('created_at', 'asc')
            ->get();

        $articleCount = Article::where('staff_id', auth()->id())->count();
        $liveServiceEnabled = Setting::bool('live_service_enabled', true);

        return view('staff.dashboard', compact('todayTickets', 'waitingTickets', 'articleCount', 'liveServiceEnabled'));
    }

    /**
     * =========================================================================
     * 2. METODE TOGGLE STATUS - UBAH STATUS KEAKTIFAN STAFF
     * =========================================================================
     * 
     * Fungsi: Mengubah status keaktifan staff (active/inactive).
     */
    public function toggleStatus(): RedirectResponse
    {
        $user = auth()->user();
        $currentStatus = $user->status;
        $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';

        if ($newStatus === 'inactive') {
            // Cek jika sedang aktif live chat (status progress)
            $activeChatExists = Ticket::where('staff_id', $user->id)
                ->where('type', 'livechat')
                ->where('status', 'progress')
                ->exists();

            if ($activeChatExists) {
                return redirect()->back()->with('error', 'Tidak dapat menonaktifkan status Anda saat sedang aktif dalam live chat.');
            }

            // Jika ada tiket live chat masuk yang belum diproses (status assigned), otomatis closed (reject)
            $assignedTickets = Ticket::where('staff_id', $user->id)
                ->where('type', 'livechat')
                ->where('status', 'assigned')
                ->get();

            foreach ($assignedTickets as $ticket) {
                $ticket->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                ]);

                // Reset staff profile to not busy
                StaffProfile::where('user_id', $user->id)->update([
                    'is_busy' => false,
                ]);

                // Log penolakan/auto-close
                TicketLog::create([
                    'ticket_id' => $ticket->id,
                    'action' => 'rejected',
                    'description' => 'Tiket otomatis ditutup (ditolak) karena staf menonaktifkan status keaktifan mereka.',
                ]);

                // Send rejection email to guest
                try {
                    Mail::to($ticket->email)->send(new TicketRejectionMail($ticket));
                } catch (\Exception $e) {
                    \Log::error('Failed to send rejection email: ' . $e->getMessage());
                }

                // Broadcast event TicketClosed
                broadcast(new TicketClosed($ticket));

                // Cari tiket waiting berikutnya untuk kategori ini untuk ditugaskan ke staf lain
                $staffProfile = StaffProfile::where('user_id', $user->id)
                    ->where('category_id', $ticket->category_id)
                    ->first();
                if ($staffProfile) {
                    $assignmentService = resolve(TicketAssignmentService::class);
                    $assignmentService->assignNextWaiting($staffProfile);
                }
            }
        }

        // Update user status
        $user->update([
            'status' => $newStatus,
        ]);

        if ($newStatus === 'active') {
            // Otomatis masukkan tiket jika ada tiket waiting
            $staffProfiles = StaffProfile::where('user_id', $user->id)->get();
            $assignmentService = resolve(TicketAssignmentService::class);
            foreach ($staffProfiles as $profile) {
                $profile->refresh();
                while (!$profile->is_busy) {
                    $assignedTicket = $assignmentService->assignNextWaiting($profile);
                    if (!$assignedTicket) {
                        break;
                    }
                    $profile->refresh();
                }
            }
        }

        $statusLabel = $newStatus === 'active' ? 'aktif' : 'nonaktif';
        return redirect()->back()->with('success', "Status keaktifan Anda berhasil diubah menjadi {$statusLabel}.");
    }
}