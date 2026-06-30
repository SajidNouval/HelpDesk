<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleFeedback;
use App\Models\Ticket;
use App\Models\Setting;
use App\Models\User;
use App\Models\ChatbotSearchLog;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;

/**
 * =============================================================================
 * ADMIN DASHBOARD CONTROLLER - DASHBOARD ADMIN
 * =============================================================================
 * 
 * Controller ini menampilkan dashboard utama untuk admin dengan statistik
 * dan ringkasan sistem helpdesk secara keseluruhan.
 * 
 * Fitur Utama:
 * - Statistik artikel (total, pending, approved, rejected)
 * - Statistik tiket (waiting, processing, done)
 * - Statistik feedback artikel (helpful vs not helpful)
 * - Performa staff (jumlah tiket diselesaikan, artikel approved/rejected)
 * - Artikel teratas berdasarkan views
 * - Staff teratas berdasarkan performa
 * - Toggle live service on/off
 * 
 * Model Terkait:
 * - Article: Statistik artikel
 * - Ticket: Statistik tiket
 * - User: Data staff dan performa
 * - ArticleFeedback: Feedback pengguna
 * - Setting: Konfigurasi sistem
 */
class DashboardController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE INDEX - TAMPILAN DASHBOARD ADMIN
     * =========================================================================
     * 
     * Fungsi: Menampilkan dashboard utama admin dengan semua statistik.
     * 
     * Alur Proses:
     * 1. Hitung jumlah staff (role = 'staff')
     * 2. Hitung total artikel
     * 3. Hitung feedback helpful dan not helpful
     * 4. Query artikel pending untuk persetujuan (oldest first)
     * 5. Query artikel dengan detail feedback dan views (top 10)
     * 6. Cek status live service
     * 7. Hitung statistik tiket (total, waiting, processing, done)
     * 8. Query performa staff dengan conditional counts
     * 9. Ambil top 5 artikel berdasarkan views
     * 10. Ambil top 3 staff berdasarkan tiket completed
     * 11. Siapkan placeholder statistik chatbot
     * 12. Kembalikan view dashboard
     * 
     * Query yang Digunakan:
     * - User::where('role', 'staff')->count(): Hitung staff
     * - Article::count(): Hitung total artikel
     * - ArticleFeedback::where('is_helpful', true/false)->count(): Hitung feedback
     * - Article::with('category', 'staff')->where('publish_status', 'pending'): 
     *   Artikel pending untuk approval
     * - Article::withCount(['feedback as helpful_count', ...]): 
     *   Artikel dengan statistik feedback
     * - Ticket::where('status', '...')->count(): Hitung tiket per status
     * - User::where('role', 'staff')->withCount([...]): 
     *   Performa staff dengan multiple conditional counts
     * 
     * Output:
     * - View 'admin.dashboard' dengan semua data statistik
     */
    public function index(): View
    {
        $version = Cache::rememberForever('admin_dashboard_version', fn() => time());
        $queryString = request()->getQueryString() ?? '';
        $cacheKey = "admin_dashboard_data:v{$version}:" . md5($queryString);

        $data = Cache::remember($cacheKey, 3600, function() {
            $staffCount = User::where('role', 'staff')->count();
            $articleCount = Article::count();
            
            $feedbackStats = ArticleFeedback::selectRaw("
                count(case when is_helpful = 1 then 1 end) as helpful,
                count(case when is_helpful = 0 then 1 end) as not_helpful
            ")->first();
            $helpfulFeedbackCount = $feedbackStats->helpful ?? 0;
            $notHelpfulFeedbackCount = $feedbackStats->not_helpful ?? 0;

            $pendingArticles = Article::select(['id', 'category_id', 'staff_id', 'title', 'slug', 'created_at'])
                ->with([
                    'category:id,name',
                    'staff:id,name'
                ])
                ->where('publish_status', 'pending')
                ->oldest()
                ->paginate(10)
                ->withQueryString();

            $articles = Article::select(['id', 'category_id', 'staff_id', 'title', 'slug', 'views', 'created_at'])
                ->with([
                    'category:id,name',
                    'staff:id,name'
                ])
                ->withCount([
                    'feedback as helpful_count' => function ($query) {
                        $query->where('is_helpful', true);
                    },
                    'feedback as not_helpful_count' => function ($query) {
                        $query->where('is_helpful', false);
                    },
                ])
                ->orderBy('views', 'desc')
                ->paginate(10)
                ->withQueryString();

            $liveServiceEnabled = Setting::bool('live_service_enabled', true);

            $ticketStats = Ticket::selectRaw("
                count(*) as total,
                count(case when status = 'waiting' then 1 end) as waiting,
                count(case when status in ('assigned', 'progress') then 1 end) as processing,
                count(case when status = 'closed' then 1 end) as done
            ")->first();
            $totalTickets = $ticketStats->total;
            $ticketsWaiting = $ticketStats->waiting;
            $ticketsProcessing = $ticketStats->processing;
            $ticketsDone = $ticketStats->done;

            $staffStats = User::select(['id', 'name', 'email'])
                ->where('role', 'staff')
                ->withCount([
                    'tickets as total_tickets',
                    'tickets as tickets_done' => function ($q) { $q->where('status', 'closed'); },
                    'tickets as tickets_waiting' => function ($q) { $q->where('status', 'waiting'); },
                    'tickets as tickets_rejected' => function ($q) { $q->whereHas('logs', function ($q2) { $q2->where('action', 'rejected'); }); },
                    'articles as articles_approved' => function ($q) { $q->where('publish_status', 'approved'); },
                    'articles as articles_rejected' => function ($q) { $q->where('publish_status', 'rejected'); },
                ])
                ->orderByDesc('tickets_done')
                ->paginate(10)
                ->withQueryString();

            $topArticles = Article::orderByDesc('views')->take(5)->get(['id', 'title', 'views']);

            $topStaff = User::where('role', 'staff')
                ->withCount(['tickets as completed_count' => function ($q) { $q->where('status', 'closed'); }])
                ->orderByDesc('completed_count')
                ->take(3)
                ->get(['id', 'name']);

            $totalChatbotQuestions = ChatbotSearchLog::count();
            $todayChatbotQuestions = ChatbotSearchLog::whereDate('created_at', \Carbon\Carbon::today())->count();
            $answeredChatbotQuestions = ChatbotSearchLog::where('is_fallback_triggered', false)->count();
            $unansweredChatbotQuestions = ChatbotSearchLog::where('is_fallback_triggered', true)->count();
            $chatbotAccuracy = $totalChatbotQuestions > 0
                ? round(($answeredChatbotQuestions / $totalChatbotQuestions) * 100, 1)
                : 0;
            $avgConfidence = ChatbotSearchLog::where('is_fallback_triggered', false)->avg('confidence') ?? 0;
            $avgConfidencePercent = round($avgConfidence * 100, 1);

            $chatbotStats = [
                'total_questions' => $totalChatbotQuestions,
                'today' => $todayChatbotQuestions,
                'answered' => $answeredChatbotQuestions,
                'unanswered' => $unansweredChatbotQuestions,
                'accuracy_rate' => $chatbotAccuracy,
                'avg_confidence' => $avgConfidencePercent,
            ];

            // Top 5 chatbot queries
            $chatbotQueries = ChatbotSearchLog::selectRaw("query_original, count(*) as query_count")
                ->groupBy('query_original')
                ->orderByDesc('query_count')
                ->take(5)
                ->get();

            // Top 5 recommended articles
            $chatbotArticles = ChatbotSearchLog::selectRaw("top_result_id, top_result_title, count(*) as recommend_count")
                ->whereNotNull('top_result_id')
                ->groupBy('top_result_id', 'top_result_title')
                ->orderByDesc('recommend_count')
                ->take(5)
                ->get();

            // Recent chatbot logs
            $recentChatbotLogs = ChatbotSearchLog::select(['id', 'query_original', 'detected_domain', 'confidence', 'is_fallback_triggered', 'created_at'])
                ->latest()
                ->paginate(5, ['*'], 'chatbot_page')
                ->withQueryString();

            return compact(
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
                'chatbotStats',
                'chatbotQueries',
                'chatbotArticles',
                'recentChatbotLogs'
            );
        });

        return view('admin.dashboard', $data);
    }

    /**
     * =========================================================================
     * 2. METODE TOGGLE LIVE SERVICE - AKTIFKAN/MATIKAN LIVE SERVICE
     * =========================================================================
     * 
     * Fungsi: Mengaktifkan atau mematikan live service (live chat).
     * 
     * Alur Proses:
     * 1. Validasi input status (on/off)
     * 2. Update setting 'live_service_enabled'
     * 3. Redirect ke dashboard dengan pesan sukses
     * 
     * Query yang Digunakan:
     * - Setting::setValue('live_service_enabled', ...): Update setting
     * 
     * Output:
     * - Redirect ke route('admin.dashboard')
     */
    public function toggleLiveService(Request $request)
    {
        $request->validate([
            'status' => 'required|in:on,off',
        ]);

        $enabled = $request->status === 'on';
        Setting::setValue('live_service_enabled', $enabled ? '1' : '0');

        return $this->safeRedirect('admin.dashboard')
            ->with('success', 'Live service telah ' . ($enabled ? 'diaktifkan' : 'dimatikan') . '.');
    }
}