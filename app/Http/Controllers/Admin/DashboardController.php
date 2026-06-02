<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleFeedback;
use App\Models\Ticket;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $staffCount = User::where('role', 'staff')->count();
        $articleCount = Article::count();
        $helpfulFeedbackCount = ArticleFeedback::where('is_helpful', true)->count();
        $notHelpfulFeedbackCount = ArticleFeedback::where('is_helpful', false)->count();

        // Artikel yang menunggu persetujuan
        $pendingArticles = Article::with('category', 'staff')
            ->where('publish_status', 'pending')
            ->latest()
            ->get();

        // Artikel dengan detail feedback dan views
        $articles = Article::with('category', 'staff')
            ->withCount([
                'feedback as helpful_count' => function ($query) {
                    $query->where('is_helpful', true);
                },
                'feedback as not_helpful_count' => function ($query) {
                    $query->where('is_helpful', false);
                },
            ])
            ->orderBy('views', 'desc')
            ->paginate(10);

        $liveServiceEnabled = Setting::bool('live_service_enabled', true);

        // Global ticket summary
        $totalTickets = Ticket::count();
        $ticketsWaiting = Ticket::where('status', 'waiting')->count();
        $ticketsProcessing = Ticket::whereIn('status', ['assigned', 'progress'])->count();
        $ticketsDone = Ticket::where('status', 'closed')->count();

        // Staff performance with efficient counts (avoid N+1)
        $staffStats = User::where('role', 'staff')
            ->withCount([
                'tickets as total_tickets',
                'tickets as tickets_done' => function ($q) { $q->where('status', 'closed'); },
                'tickets as tickets_waiting' => function ($q) { $q->where('status', 'waiting'); },
                'tickets as tickets_rejected' => function ($q) { $q->whereHas('logs', function ($q2) { $q2->where('action', 'rejected'); }); },
                'articles as articles_approved' => function ($q) { $q->where('publish_status', 'approved'); },
                'articles as articles_rejected' => function ($q) { $q->where('publish_status', 'rejected'); },
            ])
            ->orderByDesc('tickets_done')
            ->paginate(5);

        // Top articles and top performers
        $topArticles = Article::orderByDesc('views')->take(5)->get(['id', 'title', 'views']);

        $topStaff = User::where('role', 'staff')
            ->withCount(['tickets as completed_count' => function ($q) { $q->where('status', 'closed'); }])
            ->orderByDesc('completed_count')
            ->take(3)
            ->get(['id', 'name']);

        // Chatbot statistics placeholders (integrate real model if available)
        $chatbotStats = [
            'total_questions' => 0,
            'today' => 0,
            'answered' => 0,
            'unanswered' => 0,
        ];

        return view('admin.dashboard', compact(
            'staffCount',
            'articleCount',
            'helpfulFeedbackCount',
            'notHelpfulFeedbackCount',
            'pendingArticles',
            'articles',
            'liveServiceEnabled',
            'totalTickets',
            'ticketsWaiting',
            'ticketsProcessing',
            'ticketsDone',
            'staffStats',
            'topArticles',
            'topStaff',
            'chatbotStats'
        ));
    }

    public function toggleLiveService(Request $request)
    {
        $request->validate([
            'status' => 'required|in:on,off',
        ]);

        $enabled = $request->status === 'on';
        Setting::setValue('live_service_enabled', $enabled ? '1' : '0');

        return redirect()->route('admin.dashboard')
            ->with('success', 'Live service telah ' . ($enabled ? 'diaktifkan' : 'dimatikan') . '.');
    }
}
