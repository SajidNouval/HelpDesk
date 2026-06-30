<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Setting;
use App\Models\Ticket;
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
}