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

class TicketController extends Controller
{
    /**
     * 📋 Tampilkan tiket yang ditugaskan ke staff
     */
    public function index(): View
    {
        $user = auth()->user();
        
        // Get tiket yang ditugaskan ke staff ini
        $tickets = Ticket::where('staff_id', $user->id)
            ->with(['category', 'user'])
            ->latest()
            ->get();

        // Pisahkan berdasarkan status
        $activeTicket = Ticket::where('staff_id', $user->id)
            ->whereIn('status', ['assigned', 'progress'])
            ->with(['category', 'user'])
            ->first();

        $completedTickets = Ticket::where('staff_id', $user->id)
            ->where('status', 'closed')
            ->with(['category', 'user'])
            ->latest()
            ->get();

        $waitingTickets = Ticket::where('staff_id', $user->id)
            ->where('status', 'waiting')
            ->with(['category', 'user'])
            ->oldest()
            ->get();

        return view('staff.tickets.index', compact('user', 'tickets', 'activeTicket', 'completedTickets', 'waitingTickets'));
    }

    /**
     * 👁️ Lihat detail tiket
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
     * 🎯 Set priority tiket
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
     * 🔄 Update status tiket menjadi progress
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

        return back()->with('success', 'Mulai mengerjakan tiket');
    }

    /**
     * ❌ Tolak tiket (assigned atau waiting)
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

        return redirect()->route('staff.tickets.index')->with('success', 'Tiket berhasil ditolak. Guest telah menerima notifikasi.');
    }

    /**
     * ✅ Tandai tiket sebagai selesai
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

        return redirect()->route('staff.tickets.index')->with('success', 'Tiket berhasil ditandai selesai!');
    }

    /**
     * ⏸️ Tangguhkan tiket sementara
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

        return back()->with('success', 'Tiket berhasil ditangguhkan. Anda dapat melanjutkannya nanti.');
    }

    /**
     * 📝 Tambah catatan/log manual oleh staff
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
     * 🔄 Reassign tiket ke staff lain
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
     * 📋 Get ticket logs untuk modal
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

