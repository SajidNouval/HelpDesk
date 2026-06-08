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
 * CONTROLLER CHATBOT
 * =========================================================================
 * 
 * Controller ini bertugas menangani seluruh interaksi antara pengguna
 * dan sistem chatbot Helpdesk TA.
 * 
 * Tanggung jawab:
 * - Menerima pertanyaan pengguna melalui endpoint chatbot.
 * - Mengelola alur percakapan, klarifikasi, dan eskalasi.
 * - Mendelegasikan permintaan ke layanan retrieval dan format respon.
 * - Menyediakan fungsi bantuan seperti greeting, klarifikasi, dan histori.
 * 
 * Modul terkait:
 * - AdvancedRetrievalService
 * - ChatbotRetrievalService
 * - ConversationFlowService
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
     * Main chatbot endpoint - advanced TF-IDF retrieval with refinement features
     * 
     * Features integrated:
     * - Multi-intent splitting
     * - Result diversification
     * - Failure escalation
     * - Conversation memory
     * - Clarification flow
     * 
     * @param Request $request
     * @return JsonResponse
     */
    /**
     * =========================================================================
     * 1. Metode Mendapatkan Respon Chatbot
     * =========================================================================
     * 
     * Metode ini digunakan untuk memproses pertanyaan pengguna melalui chatbot.
     * 
     * Alur proses:
     * 1. Menerima dan memvalidasi input pesan pengguna.
     * 2. Mendeteksi greeting dan mengembalikan respon salam.
     * 3. Mengecek kebutuhan klarifikasi untuk pertanyaan ambigu.
     * 4. Menjalankan retrieval melalui AdvancedRetrievalService.
     * 5. Menyimpan konteks percakapan dan membentuk respon akhir.
     * 
     * Parameter:
     * Request $request
     * 
     * Return:
     * JsonResponse
     */
    public function getResponse(Request $request): JsonResponse
    {
        // Validate input
        $request->validate([
            'message' => 'required|string|max:1000',
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
     * Legacy search endpoint - uses Advanced TF-IDF retrieval
     * 
     * @param Request $request
     * @return JsonResponse
     */
    /**
     * =========================================================================
     * 2. Metode Pencarian Chatbot
     * =========================================================================
     * 
     * Metode ini menyediakan endpoint pencarian chatbot untuk query manual.
     * 
     * Alur proses:
     * 1. Menerima parameter query dari request.
     * 2. Memvalidasi input dan memanggil layanan retrieval.
     * 3. Mengembalikan hasil pencarian dalam format JSON.
     * 
     * Parameter:
     * Request $request
     * 
     * Return:
     * JsonResponse
     */
    public function chatbotSearch(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:3|max:255',
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
     * Check if escalation is needed for a query
     * 
     * @param Request $request
     * @return JsonResponse
     */
    /**
     * =========================================================================
     * 3. Metode Memeriksa Eskalasi Chatbot
     * =========================================================================
     * 
     * Metode ini menentukan apakah pertanyaan pengguna memerlukan eskalasi
     * ke tim support atau tiket.
     * 
     * Parameter:
     * Request $request
     * 
     * Return:
     * JsonResponse
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
     * Get clarification for ambiguous query
     * 
     * @param Request $request
     * @return JsonResponse
     */
    /**
     * =========================================================================
     * 4. Metode Mendapatkan Klarifikasi
     * =========================================================================
     * 
     * Metode ini memberikan pertanyaan klarifikasi ketika query pengguna
     * teridentifikasi sebagai ambigu.
     * 
     * Parameter:
     * Request $request
     * 
     * Return:
     * JsonResponse
     */
    public function getClarification(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:500',
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
     * Get conversation history
     * 
     * @param Request $request
     * @return JsonResponse
     */
    /**
     * =========================================================================
     * 5. Metode Mendapatkan Histori Percakapan
     * =========================================================================
     * 
     * Metode ini mengambil konteks percakapan terakhir dari sesi chatbot.
     * 
     * Parameter:
     * Request $request
     * 
     * Return:
     * JsonResponse
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
     * Clear conversation history
     * 
     * @param Request $request
     * @return JsonResponse
     */
    /**
     * =========================================================================
     * 6. Metode Menghapus Context Percakapan
     * =========================================================================
     * 
     * Metode ini membersihkan memori percakapan chatbot untuk memulai ulang
     * sesi interaksi.
     * 
     * Parameter:
     * Request $request
     * 
     * Return:
     * JsonResponse
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
     * Show contact form for ticket creation
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
     * Create ticket and initial message
     */
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

    /**
     * Send message to existing ticket
     */
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

    /**
     * Get messages for a ticket
     */
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

    /**
     * Get dynamic topics for greeting
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
     * Get subtopics for a category
     */
    public function getSubtopics(Request $request): JsonResponse
    {
        $request->validate([
            'topic' => 'required|string|max:255',
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
     * Get article suggestion
     */
    public function getArticleSuggestion(Request $request): JsonResponse
    {
        $request->validate([
            'article_id' => 'required|integer|exists:articles,id',
        ]);

        $article = \App\Models\Article::where('is_published', true)
            ->where('publish_status', 'approved')
            ->with('category')
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
     * Rebuild chatbot cache (admin only)
     * Note: This is a placeholder - actual cache rebuilding would require
     * rebuilding the TF-IDF index which is handled by the ReindexChatbotArticles command
     */
    public function rebuildCache(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Gunakan command: php artisan chatbot:reindex untuk membangun ulang cache TF-IDF.',
        ]);
    }

    /**
     * Clear chatbot cache (admin only)
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
     * Get greeting with category chips
     * GET /chatbot/greeting
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
     * Get subtopics for a category
     * POST /chatbot/category-subtopics
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
     * Check if query is ambiguous
     * POST /chatbot/check-ambiguity
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
     * Get search suggestions
     * GET /chatbot/search-suggestions
     */
    public function getSearchSuggestions(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));
        Log::debug('Chatbot search suggestions', ['query' => $query]);

        $suggestions = $this->conversationFlowService->getSearchSuggestions($query, 5);

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Error response helper
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
     * Generate short summary from excerpt or content (2-4 sentences)
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
     * Strip HTML tags from text
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
     * Check if text is too similar to title (likely just a description)
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
     * Extract first paragraph from text
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
     * Extract N to M sentences from text
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