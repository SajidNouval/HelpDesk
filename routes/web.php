<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Staff\TicketController as StaffTicketController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ChatbotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    if (auth()->user()->role === 'admin') {
        return redirect()->route('admin.dashboard');
    }

    if (auth()->user()->role === 'staff') {
        return redirect()->route('staff.dashboard');
    }

    abort(403, 'Akses ditolak');
})->middleware('auth')->name('dashboard');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])
        ->name('dashboard');
    Route::post('/live-service/toggle', [AdminDashboardController::class, 'toggleLiveService'])
        ->name('live-service.toggle');

    Route::resource('/users', AdminUserController::class)
        ->except(['show']);

    Route::resource('/categories', AdminCategoryController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::post('/categories/{staff}/assign-multiple', [AdminCategoryController::class, 'assignStaffCategories'])
        ->name('categories.assign-multiple');

    Route::get('/articles', [\App\Http\Controllers\Admin\ArticleController::class, 'index'])
        ->name('articles.index');
    Route::get('/articles/{article}', [\App\Http\Controllers\Admin\ArticleController::class, 'show'])
        ->name('articles.show');
    Route::post('/articles/{article}/reset-views', [\App\Http\Controllers\Admin\ArticleController::class, 'resetViews'])
        ->name('articles.reset-views');
    Route::post('/articles/{article}/reset-feedback', [\App\Http\Controllers\Admin\ArticleController::class, 'resetFeedback'])
        ->name('articles.reset-feedback');
    Route::post('/articles/{article}/toggle-hide', [\App\Http\Controllers\Admin\ArticleController::class, 'toggleHide'])
        ->name('articles.toggle-hide');
    Route::post('/articles/{article}/approve', [\App\Http\Controllers\Admin\ArticleController::class, 'approve'])
        ->name('articles.approve');
    Route::post('/articles/{article}/reject', [\App\Http\Controllers\Admin\ArticleController::class, 'reject'])
        ->name('articles.reject');
    Route::post('/articles/{article}/store-rejection-note', [\App\Http\Controllers\Admin\ArticleController::class, 'storeRejectionNote'])
        ->name('articles.store-rejection-note');
});

Route::middleware(['auth', 'staff'])->group(function () {
    Route::get('/staff/dashboard', [StaffDashboardController::class, 'index'])
        ->name('staff.dashboard');

    // Staff Tickets
    Route::get('/staff/tickets', [StaffTicketController::class, 'index'])
        ->name('staff.tickets.index');
    Route::get('/staff/tickets/{ticket}', [StaffTicketController::class, 'show'])
        ->name('staff.tickets.show');
    Route::patch('/staff/tickets/{ticket}/priority', [StaffTicketController::class, 'updatePriority'])
        ->name('staff.tickets.update-priority');
    Route::patch('/staff/tickets/{ticket}/start-progress', [StaffTicketController::class, 'startProgress'])
        ->name('staff.tickets.start-progress');
    Route::patch('/staff/tickets/{ticket}/reject', [StaffTicketController::class, 'reject'])
        ->name('staff.tickets.reject');
    Route::patch('/staff/tickets/{ticket}/complete', [StaffTicketController::class, 'complete'])
        ->name('staff.tickets.complete');
    Route::patch('/staff/tickets/{ticket}/suspend', [StaffTicketController::class, 'suspend'])
        ->name('staff.tickets.suspend');
    Route::get('/staff/tickets/{ticket}/logs', [StaffTicketController::class, 'getLogs'])
        ->name('staff.tickets.logs');
    Route::post('/staff/tickets/{ticket}/logs', [StaffTicketController::class, 'storeLog'])
        ->name('staff.tickets.logs.store');

    Route::resource('/staff/articles', ArticleController::class)->names([
        'index' => 'staff.articles.index',
        'create' => 'staff.articles.create',
        'store' => 'staff.articles.store',
        'show' => 'staff.articles.show',
        'edit' => 'staff.articles.edit',
        'update' => 'staff.articles.update',
        'destroy' => 'staff.articles.destroy',
    ]);

    Route::post('/staff/articles/{article}/reset-views', [ArticleController::class, 'resetViews'])
        ->name('staff.articles.reset-views');
    Route::post('/staff/articles/{article}/reset-feedback', [ArticleController::class, 'resetFeedback'])
        ->name('staff.articles.reset-feedback');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Public routes - Guest Tickets
Route::get('/help', [TicketController::class, 'create'])->name('guest.help');
Route::post('/tickets/request-otp', [TicketController::class, 'requestOtp'])->name('tickets.request-otp');
Route::post('/tickets/verify-otp', [TicketController::class, 'verifyOtp'])->name('tickets.verify-otp');
Route::get('/tickets/track/{token}', [TicketController::class, 'track'])->name('tickets.track');
Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
Route::post('/reports', [TicketController::class, 'storeReport'])->name('reports.store');

// Test routes — only available in local/development environment
if (app()->environment('local', 'testing')) {
    Route::get('/test-websocket', function () {
        broadcast(new \App\Events\TestWebSocketEvent('Hello from Laravel!'));
        return 'Event broadcasted!';
    });

    Route::get('/test-message-broadcast', function () {
        $message = \App\Models\Message::first();
        if ($message) {
            broadcast(new \App\Events\MessageSent($message));
            return 'Message broadcasted!';
        }
        return 'No messages found!';
    });
}

Route::get('/articles', [ArticleController::class, 'publicIndex'])->name('articles.index');
Route::get('/articles/{slug}', [ArticleController::class, 'publicShow'])->name('articles.show');
Route::post('/articles/{article}/feedback', [ArticleController::class, 'storeFeedback'])->name('articles.feedback');

// Chatbot routes (public) - dengan rate limiting untuk mencegah abuse
Route::middleware(['throttle:30,1'])->group(function () {
    Route::post('/chatbot/get-response', [ChatbotController::class, 'getResponse'])->name('chatbot.get-response');
    Route::post('/chatbot/search', [ChatbotController::class, 'chatbotSearch'])->name('chatbot.search');
    Route::post('/chatbot/show-contact-form', [ChatbotController::class, 'showContactForm'])->name('chatbot.show-contact-form');
    Route::post('/chatbot/create-ticket', [ChatbotController::class, 'createTicketAndMessage'])->name('chatbot.create-ticket');
    Route::post('/chatbot/send-message', [ChatbotController::class, 'sendMessage'])->name('chatbot.send-message');
    Route::get('/chatbot/ticket/{ticket}/messages', [ChatbotController::class, 'getTicketMessages'])->name('chatbot.messages');
});

// Interactive chatbot features (public) - rate limit ringan
Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/chatbot/greeting', [ChatbotController::class, 'getGreeting'])->name('chatbot.greeting');
    Route::post('/chatbot/category-subtopics', [ChatbotController::class, 'getCategorySubtopics'])->name('chatbot.category-subtopics');
    Route::post('/chatbot/check-ambiguity', [ChatbotController::class, 'checkAmbiguity'])->name('chatbot.check-ambiguity');
    Route::get('/chatbot/search-suggestions', [ChatbotController::class, 'getSearchSuggestions'])->name('chatbot.search-suggestions');
});

// Legacy routes (keep for backward compatibility)
Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/chatbot/topics', [ChatbotController::class, 'getTopics'])->name('chatbot.topics');
    Route::post('/chatbot/subtopics', [ChatbotController::class, 'getSubtopics'])->name('chatbot.subtopics');
    Route::post('/chatbot/article-suggestion', [ChatbotController::class, 'getArticleSuggestion'])->name('chatbot.article-suggestion');
});

// Chatbot admin routes (cache management)
Route::middleware(['auth', 'admin'])->prefix('admin/chatbot')->name('admin.chatbot.')->group(function () {
    Route::post('/rebuild-cache', [ChatbotController::class, 'rebuildCache'])->name('rebuild-cache');
    Route::post('/clear-cache', [ChatbotController::class, 'clearCache'])->name('clear-cache');
});

require __DIR__.'/auth.php';
