<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleFeedback;
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

        return view('admin.dashboard', compact(
            'staffCount',
            'articleCount',
            'helpfulFeedbackCount',
            'notHelpfulFeedbackCount',
            'pendingArticles',
            'articles',
            'liveServiceEnabled'
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
