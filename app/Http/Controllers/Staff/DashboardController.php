<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Setting;
use App\Models\Ticket;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $todayTickets = Ticket::whereDate('created_at', today())
            ->where('status', '!=', 'closed')
            ->where('staff_id', auth()->id())
            ->with(['category', 'user'])
            ->orderBy('created_at', 'asc')
            ->get();

        $waitingTickets = Ticket::where('status', 'waiting')
            ->whereNull('staff_id')
            ->with(['category', 'user'])
            ->orderBy('created_at', 'asc')
            ->get();

        $articleCount = Article::where('staff_id', auth()->id())->count();
        $liveServiceEnabled = Setting::bool('live_service_enabled', true);

        return view('staff.dashboard', compact('todayTickets', 'waitingTickets', 'articleCount', 'liveServiceEnabled'));
    }
}
