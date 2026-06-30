<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Message;
use App\Models\Category;
use App\Services\Chatbot\AdvancedRetrievalService;
use App\Services\Chatbot\ConversationFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * =========================================================================
 * CHATBOT CONTROLLER - PENGELOLAAN CHATBOT
 * =========================================================================
 *
 * Controller ini bertanggung jawab untuk menangani seluruh interaksi antara
 * pengguna dan sistem chatbot Helpdesk TA.
 *
 * Fitur Utama:
 * - Menerima pertanyaan pengguna melalui endpoint chatbot
 * - Mengelola alur percakapan, klarifikasi, dan eskalasi
 * - Mendelegasikan permintaan ke layanan retrieval dan format respon
 * - Menyediakan fungsi bantuan seperti greeting, klarifikasi, dan histori
 * - Pembuatan tiket dari chatbot
 * - Pencarian artikel dan saran topik
 *
 * Service Terkait:
 * - AdvancedRetrievalService: Layanan retrieval artikel
 * - ConversationFlowService: Layanan alur percakapan
 */
class ChatbotController extends Controller
{
    private AdvancedRetrievalService $retrievalService;
    private ConversationFlowService $conversationFlowService;

    public function __construct(
        AdvancedRetrievalService $retrievalService,
        ConversationFlowService $conversationFlowService
    ) {
        $this->retrievalService = $retrievalService;
        $this->conversationFlowService = $conversationFlowService;
    }

    /**
     * =========================================================================
     * 1. METODE GET RESPONSE - DAPATKAN RESPON CHATBOT
     * =========================================================================
     *
     * Fungsi:
     * Memproses pertanyaan pengguna melalui chatbot dengan advanced retrieval.
     *
     * Alur Proses:
     * 1. Validasi input pesan pengguna.
     * 2. Cek panjang minimum pesan.
     * 3. Deteksi greeting dan kembalikan respon salam.
     * 4. Cek kebutuhan klarifikasi untuk pertanyaan ambigu.
     * 5. Jalankan retrieval melalui AdvancedRetrievalService.
     * 6. Simpan konteks percakapan.
     * 7. Format response dengan informasi diversifikasi.
     *
     * Query yang Digunakan:
     * - $this->retrievalService->retrieve(): Retrieval artikel
     * - $this->retrievalService->formatResponse(): Format response
     *
     * Output:
     * - JsonResponse dengan data artikel dan response chatbot.
     */
    public function getResponse(Request $request): JsonResponse
    {
        // Validate input
        $request->validate([
            'message' => 'required|string',
        ]);

        $userMessage = trim($request->input('message'));

        // Validate minimum length
        if (mb_strlen($userMessage) < 3) {
            return $this->errorResponse('Pertanyaan terlalu pendek. Silakan jelaskan masalah Anda lebih detail.');
        }

        // Log query for debugging
        Log::debug('Chatbot query', [
            'query' => $userMessage,
            'is_greeting' => $this->retrievalService->isGreeting($userMessage),
        ]);

        // 1. Handle greetings (lightweight rule-based)
        if ($this->retrievalService->isGreeting($userMessage)) {
            // Clear conversation memory on new greeting
            $this->retrievalService->clearConversationMemory();
            
            return response()->json([
                'success'  => true,
                'response' => $this->retrievalService->getGreetingResponse(),
                'articles' => [],
                'categories' => $this->retrievalService->getCuratedCategories(),
            ]);
        }

        // 1.5 Handle direct escalation/admin/ticket requests
        if ($this->retrievalService->isEscalationRequest($userMessage)) {
            return response()->json([
                'success' => true,
                'response' => 'Baik, silakan klik tombol di bawah ini untuk terhubung dengan staf kami via Live Chat atau membuat tiket laporan.',
                'articles' => [],
                'show_contact_button' => true,
                'contact_button_text' => 'Buat Tiket / Hubungi Staff',
                'confidence' => 'high',
            ]);
        }

        // 2. Check for clarification needs (ambiguous queries)
        if ($this->retrievalService->needsClarification($userMessage)) {
            $clarification = $this->retrievalService->getClarificationResponse($userMessage);
            
            // Store in conversation memory
            $this->retrievalService->storeConversationContext([
                'type' => 'clarification_requested',
                'query' => $userMessage,
            ]);
            
            return response()->json($clarification);
        }

        // 3. Perform retrieval (handles multi-intent splitting internally)
        $result = $this->retrievalService->retrieve($userMessage, 5);
        
        // Store conversation context
        $this->retrievalService->storeConversationContext([
            'type' => 'retrieval',
            'query' => $userMessage,
            'found_results' => !empty($result['results']),
            'result_count' => count($result['results'] ?? []),
        ]);

        // 4. Format response (includes escalation check)
        $response = $this->retrievalService->formatResponse($result);

        // 5. Add diversification info to response
        if (!empty($result['results'])) {
            $categories = array_unique(array_column($result['results'], 'category_name'));
            $response['diversity'] = [
                'categories' => count($categories),
                'is_diverse' => count($categories) > 1,
            ];
            
            // Multi-intent info
            if (!empty($result['is_multi_intent'])) {
                $response['multi_intent'] = [
                    'detected' => true,
                    'intents' => $result['intents'] ?? [],
                ];
            }
        }

        // Log retrieval result for debugging
        Log::debug('Chatbot retrieval result', [
            'query' => $userMessage,
            'found' => count($response['articles'] ?? []),
            'confidence' => $response['confidence'] ?? 'none',
            'escalated' => $response['should_escalate'] ?? false,
        ]);

        return response()->json($response);
    }

    /**
     * =========================================================================
     * 2. METODE CHATBOT SEARCH - PENCARIAN CHATBOT
     * =========================================================================
     *
     * Fungsi:
     * Menyediakan endpoint pencarian chatbot untuk query manual.
     *
     * Alur Proses:
     * 1. Validasi input query.
     * 2. Panggil layanan retrieval dengan advanced TF-IDF.
     * 3. Map hasil ke format yang sesuai.
     * 4. Kembalikan hasil pencarian dalam format JSON.
     *
     * Query yang Digunakan:
     * - $this->retrievalService->retrieve(): Retrieval artikel
     *
     * Output:
     * - JsonResponse dengan hasil pencarian artikel.
     */
    public function chatbotSearch(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:3',
        ]);

        $keyword = $request->q;

        // Use advanced TF-IDF retrieval with multi-intent support
        $result = $this->retrievalService->retrieve($keyword, 5);

        $results = collect($result['results'])->map(fn($article) => [
            'title'    => $article['title'],
            'category' => $article['category_name'] ?? '-',
            'excerpt'  => $article['excerpt'] ?? '',
            'url'      => $article['url'],
            'confidence' => $article['confidence'] ?? 'medium',
        ]);

        return response()->json([
            'query'        => $keyword,
            'results'      => $results,
            'total'        => count($results),
            'is_multi_intent' => $result['is_multi_intent'] ?? false,
            'intents'      => $result['intents'] ?? [],
        ]);
    }
    
    /**
     * =========================================================================
     * 3. METODE CHECK ESCALATION - CEK ESKALASI
     * =========================================================================
     *
     * Fungsi:
     * Menentukan apakah pertanyaan pengguna memerlukan eskalasi ke tim support.
     *
     * Alur Proses:
     * 1. Validasi input pesan.
     * 2. Cek apakah query perlu eskalasi.
     * 3. Hitung jumlah failure.
     * 4. Kembalikan response dengan status eskalasi.
     *
     * Query yang Digunakan:
     * - $this->retrievalService->shouldEscalate(): Cek kebutuhan eskalasi
     * - $this->retrievalService->getFailureCount(): Hitung failure
     *
     * Output:
     * - JsonResponse dengan status eskalasi dan response eskalasi.
     */
    public function checkEscalation(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $message = trim($request->input('message'));
        
        $shouldEscalate = $this->retrievalService->shouldEscalate($message);
        $failureCount = $this->retrievalService->getFailureCount($message);
        
        return response()->json([
            'success' => true,
            'should_escalate' => $shouldEscalate,
            'failure_count' => $failureCount,
            'threshold' => 3,
            'escalation_response' => $shouldEscalate ? $this->retrievalService->getEscalationResponse() : null,
        ]);
    }
    
    /**
     * =========================================================================
     * 4. METODE GET CLARIFICATION - DAPATKAN KLARIFIKASI
     * =========================================================================
     *
     * Fungsi:
     * Memberikan pertanyaan klarifikasi ketika query pengguna ambigu.
     *
     * Alur Proses:
     * 1. Validasi input pesan.
     * 2. Cek apakah query memerlukan klarifikasi.
     * 3. Jika ya, kembalikan response klarifikasi.
     * 4. Jika tidak, kembalikan response tanpa klarifikasi.
     *
     * Query yang Digunakan:
     * - $this->retrievalService->needsClarification(): Cek kebutuhan klarifikasi
     * - $this->retrievalService->getClarificationResponse(): Dapatkan respon klarifikasi
     *
     * Output:
     * - JsonResponse dengan data klarifikasi.
     */
    public function getClarification(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $message = trim($request->input('message'));
        
        $needsClarification = $this->retrievalService->needsClarification($message);
        
        if ($needsClarification) {
            return response()->json($this->retrievalService->getClarificationResponse($message));
        }
        
        return response()->json([
            'success' => true,
            'needs_clarification' => false,
        ]);
    }
    
    /**
     * =========================================================================
     * 5. METODE GET CONVERSATION HISTORY - HISTORI PERCAKAPAN
     * =========================================================================
     *
     * Fungsi:
     * Mengambil konteks percakapan terakhir dari sesi chatbot.
     *
     * Alur Proses:
     * 1. Ambil parameter limit dari request.
     * 2. Ambil history percakapan dari service.
     * 3. Kembalikan response dengan history percakapan.
     *
     * Query yang Digunakan:
     * - $this->retrievalService->getRecentConversationContext(): Ambil history
     *
     * Output:
     * - JsonResponse dengan array history percakapan.
     */
    public function getConversationHistory(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 5);
        $history = $this->retrievalService->getRecentConversationContext($limit);
        
        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }
    
    /**
     * =========================================================================
     * 6. METODE CLEAR CONVERSATION - HAPUS PERCAKAPAN
     * =========================================================================
     *
     * Fungsi:
     * Membersihkan memori percakapan chatbot untuk memulai ulang sesi.
     *
     * Alur Proses:
     * 1. Panggil method clearConversationMemory dari service.
     * 2. Kembalikan response sukses.
     *
     * Query yang Digunakan:
     * - $this->retrievalService->clearConversationMemory(): Hapus memory
     *
     * Output:
     * - JsonResponse dengan pesan sukses.
     */
    public function clearConversation(Request $request): JsonResponse
    {
        $this->retrievalService->clearConversationMemory();
        
        return response()->json([
            'success' => true,
            'message' => 'Conversation history cleared',
        ]);
    }

    /**
     * =========================================================================
     * 7. METODE SHOW CONTACT FORM - TAMPILKAN FORM KONTAK
     * =========================================================================
     *
     * Fungsi:
     * Menampilkan form kontak untuk pembuatan tiket dari chatbot.
     *
     * Alur Proses:
     * 1. Ambil semua kategori.
     * 2. Format form fields untuk response.
     * 3. Kembalikan response dengan data form.
     *
     * Query yang Digunakan:
     * - Category::select()->orderBy()->get(): Ambil kategori
     *
     * Output:
     * - JsonResponse dengan data form kontak.
     */
    public function showContactForm(): JsonResponse
    {
        $categories = Category::select('id', 'name')->orderBy('name')->get();

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

    /**
     * =========================================================================
     * 8. METODE CREATE TICKET AND MESSAGE - BUAT TIKET DAN PESAN
     * =========================================================================
     *
     * Fungsi:
     * Membuat tiket baru dan pesan awal dari chatbot.
     *
     * Alur Proses:
     * 1. Validasi input tiket.
     * 2. Buat tiket baru dengan status open.
     * 3. Insert pesan awal dari guest dan bot.
     * 4. Kembalikan response dengan data tiket.
     *
     * Query yang Digunakan:
     * - Ticket::create(): Buat tiket baru
     * - Message::insert(): Insert pesan awal
     *
     * Output:
     * - JsonResponse dengan data tiket yang dibuat.
     */
    public function createTicketAndMessage(Request $request): JsonResponse
    {
        $request->validate([
            'title'       => 'required|string|max:200',
            'message'     => 'required|string|max:2000',
            'category_id' => 'required|exists:categories,id',
            'email'       => 'nullable|email|max:50',
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

        // Simpan ke session kepemilikan tiket
        $myTickets = session()->get('my_tickets', []);
        $myTickets[] = $ticket->id;
        session(['my_tickets' => $myTickets, 'guest_ticket_id' => $ticket->id]);
        if ($request->email) {
            session(['guest_email' => $request->email]);
        }
        session()->save();

        return response()->json([
            'success'   => true,
            'ticket_id' => $ticket->id,
            'message'   => 'Tiket berhasil dibuat. Tim kami akan segera menghubungi Anda.',
        ]);
    }

    /**
     * =========================================================================
     * 9. METODE SEND MESSAGE - KIRIM PESAN KE TIKET
     * =========================================================================
     *
     * Fungsi:
     * Mengirim pesan ke tiket yang sudah ada.
     *
     * Alur Proses:
     * 1. Validasi input pesan dan ticket_id.
     * 2. Cek status tiket (tidak boleh closed).
     * 3. Buat pesan baru dengan sender_type yang sesuai.
     * 4. Kembalikan response sukses.
     *
     * Query yang Digunakan:
     * - Ticket::findOrFail(): Ambil tiket
     * - Message::create(): Buat pesan baru
     *
     * Output:
     * - JsonResponse dengan pesan sukses.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'message'   => 'required|string|max:2000',
        ]);

        $ticket = Ticket::select(['id', 'status'])->findOrFail($request->ticket_id);

        $myTickets = session()->get('my_tickets', []);
        $guestTicketId = session('guest_ticket_id');
        $isStaff = auth()->check() && auth()->user()->role === 'staff';
        $isAdmin = auth()->check() && auth()->user()->role === 'admin';
        $isOwner = in_array($ticket->id, $myTickets) || $guestTicketId == $ticket->id;

        if (!$isStaff && !$isAdmin && !$isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

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

    /**
     * =========================================================================
     * 10. METODE GET TICKET MESSAGES - AMBIL PESAN TIKET
     * =========================================================================
     *
     * Fungsi:
     * Mengambil semua pesan dalam sebuah tiket.
     *
     * Alur Proses:
     * 1. Query semua pesan dalam tiket dengan urutan ascending.
     * 2. Kembalikan response dengan data pesan dan tiket.
     *
     * Query yang Digunakan:
     * - $ticket->messages()->orderBy()->get(): Ambil pesan tiket
     *
     * Output:
     * - JsonResponse dengan array pesan dan data tiket.
     */
    public function getTicketMessages(Request $request, Ticket $ticket): JsonResponse
    {
        $myTickets = session()->get('my_tickets', []);
        $guestTicketId = session('guest_ticket_id');
        $isStaff = auth()->check() && auth()->user()->role === 'staff';
        $isAdmin = auth()->check() && auth()->user()->role === 'admin';
        $isOwner = in_array($ticket->id, $myTickets) || $guestTicketId == $ticket->id;

        if (!$isStaff && !$isAdmin && !$isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

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

    /**
     * =========================================================================
     * 11. METODE GET TOPICS - DAPATKAN TOPIK
     * =========================================================================
     *
     * Fungsi:
     * Mengambil topik dinamis untuk greeting chatbot.
     *
     * Alur Proses:
     * 1. Panggil conversation flow service untuk data greeting.
     * 2. Ambil greeting response dari retrieval service.
     * 3. Kembalikan response dengan topik dan greeting.
     *
     * Query yang Digunakan:
     * - $this->conversationFlowService->getGreetingData(): Ambil data greeting
     * - $this->retrievalService->getGreetingResponse(): Ambil respon greeting
     *
     * Output:
     * - JsonResponse dengan topik dan greeting.
     */
    public function getTopics(): JsonResponse
    {
        // Use conversation flow service for topics
        $data = $this->conversationFlowService->getGreetingData();

        return response()->json([
            'success' => true,
            'topics' => $data['categories'],
            'greeting' => $this->retrievalService->getGreetingResponse(),
        ]);
    }

    /**
     * =========================================================================
     * 12. METODE GET SUBTOPICS - DAPATKAN SUBTOPIK
     * =========================================================================
     *
     * Fungsi:
     * Mengambil subtopics untuk kategori tertentu.
     *
     * Alur Proses:
     * 1. Validasi input topik.
     * 2. Cek curated subtopics dari retrieval service.
     * 3. Jika tidak ada, gunakan fallback dari conversation flow service.
     * 4. Kembalikan response dengan subtopics.
     *
     * Query yang Digunakan:
     * - $this->retrievalService->getCuratedSubtopics(): Ambil subtopics
     * - $this->conversationFlowService->getSearchSuggestions(): Fallback saran
     *
     * Output:
     * - JsonResponse dengan array subtopics.
     */
    public function getSubtopics(Request $request): JsonResponse
    {
        $request->validate([
            'topic' => 'required|string',
        ]);

        // Map topic to category or use curated subtopics
        $topic = strtolower($request->topic);
        $subtopics = $this->retrievalService->getCuratedSubtopics($topic);
        
        if (empty($subtopics)) {
            // Fallback to conversation flow service
            $data = $this->conversationFlowService->getSearchSuggestions($topic, 4);
            $subtopics = array_map(fn($s) => [
                'id' => $s['id'],
                'label' => $s['label'],
                'slug' => $s['slug'] ?? null,
            ], $data);
        }

        return response()->json([
            'success' => true,
            'subtopics' => $subtopics,
        ]);
    }

    /**
     * =========================================================================
     * 13. METODE GET ARTICLE SUGGESTION - DAPATKAN SARAN ARTIKEL
     * =========================================================================
     *
     * Fungsi:
     * Mengambil saran artikel terkait berdasarkan artikel yang dipilih.
     *
     * Alur Proses:
     * 1. Validasi input article_id.
     * 2. Query artikel yang published dan approved.
     * 3. Gunakan retrieval service untuk mencari artikel terkait.
     * 4. Generate summary dari excerpt atau konten.
     * 5. Kembalikan response dengan artikel dan artikel terkait.
     *
     * Query yang Digunakan:
     * - Article::where()->with()->find(): Ambil artikel
     * - $this->retrievalService->retrieve(): Cari artikel terkait
     *
     * Output:
     * - JsonResponse dengan artikel dan artikel terkait.
     */
    public function getArticleSuggestion(Request $request): JsonResponse
    {
        $request->validate([
            'article_id' => 'required|string|exists:articles,id',
        ]);

        $article = \App\Models\Article::select(['id', 'category_id', 'title', 'excerpt', 'content', 'slug'])
            ->where('is_published', true)
            ->where('publish_status', 'approved')
            ->with('category:id,name')
            ->find($request->article_id);

        if (!$article) {
            return response()->json([
                'success' => false,
                'message' => 'Artikel tidak ditemukan.',
            ], 404);
        }

        // Use retrieval service to find related articles
        $relatedResult = $this->retrievalService->retrieve($article->title . ' ' . $article->excerpt, 3);
        $related = array_filter($relatedResult['results'] ?? [], fn($r) => $r['id'] != $article->id);

        // Generate summary from excerpt or content
        $summary = $this->generateSummaryFromExcerpt($article->excerpt ?? '', $article->content ?? '', $article->title ?? '');
        $response = $summary . "\n\nArtikel berikut mungkin dapat membantu Anda:";

        return response()->json([
            'success' => true,
            'response' => $response,
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'slug' => $article->slug,
                'url' => route('articles.show', $article->slug),
            ],
            'related' => array_slice(array_values($related), 0, 2),
        ]);
    }

    /**
     * =========================================================================
     * 14. METODE REBUILD CACHE - REBUILD CACHE CHATBOT
     * =========================================================================
     *
     * Fungsi:
     * Placeholder untuk rebuild cache chatbot (admin only).
     *
     * Alur Proses:
     * 1. Kembalikan response dengan instruksi untuk rebuild cache.
     *
     * Output:
     * - JsonResponse dengan instruksi rebuild cache.
     */
    public function rebuildCache(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Gunakan command: php artisan chatbot:reindex untuk membangun ulang cache TF-IDF.',
        ]);
    }

    /**
     * =========================================================================
     * 15. METODE CLEAR CACHE - HAPUS CACHE CHATBOT
     * =========================================================================
     *
     * Fungsi:
     * Menghapus cache chatbot (admin only).
     *
     * Alur Proses:
     * 1. Hapus session-based caches.
     * 2. Flush application cache.
     * 3. Kembalikan response sukses.
     *
     * Output:
     * - JsonResponse dengan pesan sukses.
     */
    public function clearCache(): JsonResponse
    {
        // Clear session-based caches
        session()->forget('chatbot_failure_memory');
        session()->forget('chatbot_conversation_memory');
        
        // Clear application cache
        \Illuminate\Support\Facades\Cache::flush();

        return response()->json([
            'success' => true,
            'message' => 'Cache chatbot berhasil dihapus.',
        ]);
    }

    /**
     * =========================================================================
     * 16. METODE GET GREETING - DAPATKAN GREETING
     * =========================================================================
     *
     * Fungsi:
     * Mengambil greeting dengan kategori chips.
     *
     * Alur Proses:
     * 1. Panggil conversation flow service untuk data greeting.
     * 2. Kembalikan response dengan greeting dan kategori.
     *
     * Query yang Digunakan:
     * - $this->conversationFlowService->getGreetingData(): Ambil data greeting
     *
     * Output:
     * - JsonResponse dengan greeting dan kategori.
     */
    public function getGreeting(): JsonResponse
    {
        Log::debug('Chatbot greeting requested');
        
        $data = $this->conversationFlowService->getGreetingData();

        return response()->json([
            'success' => true,
            'greeting' => $data['greeting'],
            'categories' => $data['categories'],
        ]);
    }

    /**
     * =========================================================================
     * 17. METODE GET CATEGORY SUBTOPICS - DAPATKAN SUBTOPIK KATEGORI
     * =========================================================================
     *
     * Fungsi:
     * Mengambil subtopics untuk kategori berdasarkan category_id.
     *
     * Alur Proses:
     * 1. Validasi input category_id.
     * 2. Panggil conversation flow service untuk subtopics.
     * 3. Cek error dan kembalikan response sesuai.
     *
     * Query yang Digunakan:
     * - $this->conversationFlowService->getCategorySubtopics(): Ambil subtopics
     *
     * Output:
     * - JsonResponse dengan subtopics atau error.
     */
    public function getCategorySubtopics(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|string|exists:categories,id',
        ]);

        $categoryId = $request->input('category_id');
        Log::debug('Chatbot category subtopics requested', ['category_id' => $categoryId]);

        $data = $this->conversationFlowService->getCategorySubtopics($categoryId);

        if (isset($data['error'])) {
            return response()->json([
                'success' => false,
                'message' => $data['error'],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'question' => $data['question'],
            'subtopics' => $data['subtopics'],
        ]);
    }

    /**
     * =========================================================================
     * 18. METODE CHECK AMBIGUITY - CEK AMBIGUITAS
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa apakah query ambigu.
     *
     * Alur Proses:
     * 1. Validasi input pesan.
     * 2. Panggil conversation flow service untuk cek ambigu.
     * 3. Kembalikan response dengan status ambigu.
     *
     * Query yang Digunakan:
     * - $this->conversationFlowService->checkAmbiguity(): Cek ambigu
     *
     * Output:
     * - JsonResponse dengan status ambigu dan klarifikasi.
     */
    public function checkAmbiguity(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $message = trim($request->input('message'));
        Log::debug('Chatbot ambiguity check', ['message' => $message]);

        $result = $this->conversationFlowService->checkAmbiguity($message);

        return response()->json([
            'success' => true,
            'is_ambiguous' => $result['is_ambiguous'] ?? false,
            'clarification' => $result['clarification'] ?? null,
        ]);
    }

    /**
     * =========================================================================
     * 19. METODE GET SEARCH SUGGESTIONS - DAPATKAN SARAN PENCARIAN
     * =========================================================================
     *
     * Fungsi:
     * Mengambil saran pencarian berdasarkan query.
     *
     * Alur Proses:
     * 1. Ambil parameter query dari request.
     * 2. Panggil conversation flow service untuk saran.
     * 3. Kembalikan response dengan saran pencarian.
     *
     * Query yang Digunakan:
     * - $this->conversationFlowService->getSearchSuggestions(): Ambil saran
     *
     * Output:
     * - JsonResponse dengan array saran pencarian.
     */
    public function getSearchSuggestions(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'nullable|string|max:200',
        ]);

        $query = trim($request->input('q', ''));
        Log::debug('Chatbot search suggestions', ['query' => $query]);

        $suggestions = $this->conversationFlowService->getSearchSuggestions($query, 5);

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * =========================================================================
     * 20. METODE ERROR RESPONSE - RESPONSE ERROR
     * =========================================================================
     *
     * Fungsi:
     * Membuat response error standar.
     *
     * Alur Proses:
     * 1. Buat response JSON dengan pesan error.
     *
     * Output:
     * - JsonResponse dengan pesan error.
     */
    private function errorResponse(string $message): JsonResponse
    {
        return response()->json([
            'success'  => false,
            'response' => $message,
            'articles' => [],
        ]);
    }

    /**
     * =========================================================================
     * 21. METODE GENERATE SUMMARY FROM EXCERPT - BUAT RINGKASAN
     * =========================================================================
     *
     * Fungsi:
     * Membuat ringkasan singkat dari excerpt atau konten artikel.
     *
     * Alur Proses:
     * 1. Strip HTML tags dari excerpt.
     * 2. Cek apakah excerpt informatif (minimal 2 kalimat).
     * 3. Gunakan excerpt atau first paragraph dari konten.
     * 4. Extract 2-4 kalimat untuk ringkasan.
     * 5. Pastikan ringkasan berakhir dengan tanda baca.
     *
     * Output:
     * - String ringkasan artikel.
     */
    private function generateSummaryFromExcerpt(string $excerpt, string $content = '', string $title = ''): string
    {
        // Check if excerpt is informative enough (not just a description)
        $excerptText = $this->stripHtmlTags($excerpt);
        $excerptSentences = preg_split('/(?<=[.!?])\s+/', $excerptText, -1, PREG_SPLIT_NO_EMPTY);

        // Use excerpt if it has at least 2 sentences and is not too similar to title
        $useExcerpt = count($excerptSentences) >= 2 && !$this->isTooSimilarToTitle($excerptText, $title);

        if ($useExcerpt) {
            $summary = $this->extractSentences($excerptText, 2, 4);
        } elseif (!empty($content)) {
            // Use first paragraph from content if excerpt is not informative
            $contentText = $this->stripHtmlTags($content);
            $firstParagraph = $this->extractFirstParagraph($contentText);
            $summary = $this->extractSentences($firstParagraph, 2, 4);
        } else {
            // Fallback
            return 'Saya menemukan beberapa informasi yang relevan dengan pertanyaan Anda.';
        }

        // Ensure it ends with proper punctuation
        if (!in_array(substr($summary, -1), ['.', '!', '?'])) {
            $summary .= '.';
        }

        return $summary;
    }

    /**
     * =========================================================================
     * 22. METODE STRIP HTML TAGS - HAPUS HTML TAGS
     * =========================================================================
     *
     * Fungsi:
     * Menghapus HTML tags dari teks.
     *
     * Alur Proses:
     * 1. Hapus HTML tags dengan strip_tags.
     * 2. Decode HTML entities.
     * 3. Normalize whitespace.
     * 4. Trim teks.
     *
     * Output:
     * - String teks tanpa HTML tags.
     */
    private function stripHtmlTags(string $html): string
    {
        // Remove HTML tags
        $text = strip_tags($html);
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * =========================================================================
     * 23. METODE IS TOO SIMILAR TO TITLE - CEK KESAMAAN DENGAN JUDUL
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa apakah teks terlalu mirip dengan judul.
     *
     * Alur Proses:
     * 1. Cek apakah judul kosong.
     * 2. Cek apakah teks mengandung judul atau sebaliknya.
     * 3. Cek apakah teks sangat pendek (< 50 karakter).
     *
     * Output:
     * - Boolean true jika terlalu mirip, false jika tidak.
     */
    private function isTooSimilarToTitle(string $text, string $title): bool
    {
        if (empty($title)) {
            return false;
        }

        $textLower = mb_strtolower($text);
        $titleLower = mb_strtolower($title);

        // Check if text contains title or title contains text
        if (str_contains($textLower, $titleLower) || str_contains($titleLower, $textLower)) {
            return true;
        }

        // Check if text is very short (less than 50 chars)
        if (mb_strlen($text) < 50) {
            return true;
        }

        return false;
    }

    /**
     * =========================================================================
     * 24. METODE EXTRACT FIRST PARAGRAPH - EKSTRAK PARAGRAF PERTAMA
     * =========================================================================
     *
     * Fungsi:
     * Mengambil paragraf pertama dari teks.
     *
     * Alur Proses:
     * 1. Split teks berdasarkan double newline.
     * 2. Ambil paragraf pertama.
     * 3. Trim dan kembalikan.
     *
     * Output:
     * - String paragraf pertama.
     */
    private function extractFirstParagraph(string $text): string
    {
        // Split by double newline or multiple newlines
        $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($paragraphs)) {
            return $text;
        }

        // Return first paragraph, cleaned
        return trim($paragraphs[0]);
    }

    /**
     * =========================================================================
     * 25. METODE EXTRACT SENTENCES - EKSTRAK KALIMAT
     * =========================================================================
     *
     * Fungsi:
     * Mengambil N sampai M kalimat dari teks.
     *
     * Alur Proses:
     * 1. Split teks menjadi kalimat berdasarkan tanda baca.
     * 2. Hitung jumlah kalimat yang akan diambil.
     * 3. Ambil kalimat sesuai batas min dan max.
     * 4. Join kalimat menjadi string.
     *
     * Output:
     * - String dengan N sampai M kalimat.
     */
    private function extractSentences(string $text, int $min, int $max): string
    {
        // Split into sentences
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($sentences)) {
            return $text;
        }

        // Take min to max sentences
        $count = min($max, max($min, count($sentences)));
        $selectedSentences = array_slice($sentences, 0, $count);

        return implode(' ', $selectedSentences);
    }
}