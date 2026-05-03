<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Chatbot;
use App\Models\Message;
use App\Models\Ticket;
use App\Services\ArticleSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ChatbotController — Pipeline:
 *
 *  1. Validasi & normalisasi input
 *  2. Chatbot rules dari database (keyword-based)
 *  3. Pencarian artikel via TF-IDF (auto-learned dari artikel yang dipublish)
 *  4. Fallback → tampilkan tombol hubungi staff
 */
class ChatbotController extends Controller
{
    public function __construct(private ArticleSearchService $searchService) {}

    // =========================================================================
    // MAIN CHATBOT ENDPOINT
    // =========================================================================

    public function getResponse(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $userMessage = trim($request->input('message'));

        if (mb_strlen($userMessage) < 3) {
            return $this->errorResponse('Pertanyaan terlalu pendek. Silakan jelaskan masalah Anda lebih detail.');
        }

        // --- Step 1: Chatbot rules dari DB ---
        $normalized = trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9\s]/', ' ', mb_strtolower($userMessage))));
        $chatbot    = $this->findChatbotRule($normalized);
        if ($chatbot) {
            $articles = $chatbot->category_id
                ? Article::where('category_id', $chatbot->category_id)
                         ->where('is_published', true)
                         ->orderByDesc('views')
                         ->limit(5)
                         ->get()
                         ->makeHidden(['content'])
                : collect();

            return response()->json([
                'success'  => true,
                'response' => $chatbot->response,
                'articles' => $articles,
            ]);
        }

        // --- Step 2: TF-IDF article search (auto-learned) ---
        $articles = $this->searchService->search($userMessage, 5);

        if ($articles->isNotEmpty()) {
            $top      = $articles->first();
            $count    = $articles->count();
            $response = $count > 1
                ? "Saya menemukan {$count} artikel yang mungkin membantu. Artikel teratas: **{$top->title}**"
                : "Saya menemukan artikel yang mungkin membantu: **{$top->title}**";

            return response()->json([
                'success'  => true,
                'response' => $response,
                'articles' => $articles->makeHidden(['content'])->values(),
            ]);
        }

        // --- Step 3: Fallback ---
        return response()->json([
            'success'             => false,
            'response'            => 'Maaf, saya belum menemukan solusi yang tepat untuk pertanyaan Anda. Silakan hubungi staff kami.',
            'articles'            => [],
            'show_contact_button' => true,
            'contact_button_text' => 'Buat Tiket untuk Bantuan Lebih Lanjut',
        ]);
    }

    public function chatbotSearch(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:3|max:255',
        ]);

        $keyword = $request->q;

        $articles = Article::where('is_published', true)
            ->where(function ($query) use ($keyword) {
                $query->where('title', 'like', "%{$keyword}%")
                      ->orWhere('excerpt', 'like', "%{$keyword}%")
                      ->orWhere('keywords', 'like', "%{$keyword}%")
                      ->orWhere('content', 'like', "%{$keyword}%");
            })
            ->with('category')
            ->select('id', 'title', 'slug', 'excerpt', 'keywords', 'category_id')
            ->limit(5)
            ->get()
            ->map(fn($article) => [
                'title'    => $article->title,
                'category' => $article->category->name ?? '-',
                'excerpt'  => $article->excerpt,
                'keywords' => $article->keywords,
                'url'      => route('articles.show', $article->slug),
            ]);

        return response()->json([
            'query'   => $keyword,
            'results' => $articles,
            'total'   => $articles->count(),
        ]);
    }

    // =========================================================================
    // CHATBOT RULES (database-driven, dikelola dari admin panel)
    // =========================================================================

    private function findChatbotRule(string $message): ?Chatbot
    {
        // Gunakan cache pendek agar tidak query berulang per request
        $chatbots = cache()->remember('chatbot:rules', 120, function () {
            return Chatbot::active()->orderByPriority()->get();
        });

        foreach ($chatbots as $chatbot) {
            foreach ($chatbot->getKeywordsArray() as $keyword) {
                if (str_contains($message, mb_strtolower(trim($keyword)))) {
                    return $chatbot;
                }
            }
        }

        return null;
    }

    // =========================================================================
    // CONTACT FORM
    // =========================================================================

    public function showContactForm(): JsonResponse
    {
        $categories = \App\Models\Category::select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'success'    => true,
            'show_form'  => true,
            'form_title' => 'Hubungi Staff Support',
            'form_fields' => [
                'title' => [
                    'type'        => 'text',
                    'label'       => 'Judul Masalah',
                    'placeholder' => 'Jelaskan masalah Anda secara singkat',
                    'required'    => true,
                ],
                'category_id' => [
                    'type'     => 'select',
                    'label'    => 'Kategori',
                    'options'  => $categories->map(fn($c) => ['value' => $c->id, 'label' => $c->name]),
                    'required' => true,
                ],
                'message' => [
                    'type'        => 'textarea',
                    'label'       => 'Detail Masalah',
                    'placeholder' => 'Jelaskan masalah Anda lebih detail agar staff dapat membantu dengan baik',
                    'required'    => true,
                ],
                'email' => [
                    'type'        => 'email',
                    'label'       => 'Email (Opsional)',
                    'placeholder' => 'nama@email.com',
                    'required'    => false,
                ],
            ],
            'submit_button_text' => 'Kirim Tiket',
        ]);
    }

    // =========================================================================
    // TICKET & MESSAGES
    // =========================================================================

    public function createTicketAndMessage(Request $request): JsonResponse
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'message'     => 'required|string|max:2000',
            'category_id' => 'required|exists:categories,id',
            'email'       => 'nullable|email|max:255',
        ]);

        $ticket = Ticket::create([
            'user_id'     => auth()->id(),
            'name'        => auth()->user()?->name ?? 'Guest User',
            'email'       => $request->email,
            'subject'     => $request->title,
            'message'     => $request->message,
            'category_id' => $request->category_id,
            'priority'    => 'low',
            'status'      => 'open',
        ]);

        Message::insert([
            [
                'ticket_id'   => $ticket->id,
                'sender_type' => 'guest',
                'message'     => $request->message,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'ticket_id'   => $ticket->id,
                'sender_type' => 'bot',
                'message'     => 'Terima kasih telah menghubungi kami. Tim support kami akan segera merespons tiket Anda.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);

        return response()->json([
            'success'   => true,
            'ticket_id' => $ticket->id,
            'message'   => 'Tiket berhasil dibuat. Tim kami akan segera menghubungi Anda.',
        ]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'message'   => 'required|string|max:2000',
        ]);

        $ticket = Ticket::findOrFail($request->ticket_id);

        if ($ticket->status === 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'Tiket ini sudah ditutup. Silakan buat tiket baru.',
            ], 422);
        }

        Message::create([
            'ticket_id'   => $ticket->id,
            'sender_type' => auth()->check() ? 'user' : 'guest',
            'message'     => $request->message,
        ]);

        return response()->json(['success' => true, 'message' => 'Pesan terkirim.']);
    }

    public function getTicketMessages(Request $request, Ticket $ticket): JsonResponse
    {
        $messages = $ticket->messages()
            ->orderBy('created_at')
            ->get(['id', 'sender_type', 'message', 'created_at']);

        return response()->json([
            'success'  => true,
            'messages' => $messages,
            'ticket'   => [
                'id'     => $ticket->id,
                'title'  => $ticket->subject,
                'status' => $ticket->status,
            ],
        ]);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function errorResponse(string $message): JsonResponse
    {
        return response()->json([
            'success'  => false,
            'response' => $message,
            'articles' => [],
        ]);
    }
}
