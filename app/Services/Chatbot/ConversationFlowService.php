<?php

namespace App\Services\Chatbot;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\Session;

/**
 * ConversationFlowService - Manages conversational flow and context
 * 
 * This service handles:
 * - Interactive greeting with random categories
 * - Guided category → subtopic flow
 * - Ambiguity detection for generic queries
 * - Multi-turn conversation context
 * - Search suggestions
 * 
 * All flows are deterministic and retrieval-based (no AI generation).
 */
class ConversationFlowService
{
    /**
     * Ambiguous query patterns that need clarification (only when standalone)
     */
    private array $ambiguousPatterns = [
        'lemot',
        'lambat',
        'error',
        'eror',
        'tidak bisa',
        'gak bisa',
        'ga bisa',
        'bermasalah',
        'masalah',
        'rusak',
        'mati',
        'hilang',
        'blank',
        'kosong',
        'no signal',
        'tidak muncul',
        'gak muncul',
    ];

    /**
     * Domain/context terms that indicate specific topic
     * When combined with issue terms, skip ambiguity check
     */
    private array $domainTerms = [
        'wifi',
        'internet',
        'printer',
        'komputer',
        'laptop',
        'software',
        'aplikasi',
        'email',
        'jaringan',
        'router',
        'modem',
        'lan',
        'server',
        'dns',
        'ip',
        'usb',
        'bluetooth',
        'monitor',
        'keyboard',
        'mouse',
        'scanner',
        'webcam',
        'speaker',
        'microphone',
        'windows',
        'linux',
        'android',
        'ios',
        'office',
        'browser',
        'chrome',
        'firefox',
        'excel',
        'word',
        'powerpoint',
        'outlook',
        'drive',
        'folder',
        'file',
        'backup',
        'install',
        'uninstall',
        'update',
        'driver',
    ];

    /**
     * Issue/problem terms
     */
    private array $issueTerms = [
        'lemot',
        'lambat',
        'error',
        'eror',
        'tidak bisa',
        'gak bisa',
        'ga bisa',
        'bermasalah',
        'masalah',
        'rusak',
        'mati',
        'hilang',
        'blank',
        'kosong',
        'no signal',
        'tidak muncul',
        'gak muncul',
        'crash',
        'hang',
        'freeze',
        'not responding',
        'blue screen',
        'overheat',
        'panas',
        'bunyi',
        'putus',
        'disconnect',
        'connect',
    ];

    /**
     * Clarification suggestions mapped to categories
     */
    private array $clarificationMap = [
        'wifi' => ['WiFi lemot', 'Tidak bisa connect', 'No internet', 'Sering putus'],
        'internet' => ['Internet lemot', 'Tidak terhubung', 'DNS error', 'IP conflict'],
        'printer' => ['Printer tidak terdeteksi', 'Macet print', 'Kertas nyangkut', 'Tinta habis'],
        'komputer' => ['Komputer lemot', 'Blue screen', 'Tidak bisa nyala', 'Overheat'],
        'software' => ['Aplikasi error', 'Tidak bisa install', 'Crash', 'Update gagal'],
    ];

    /**
     * Get interactive greeting with random categories
     * 
     * @return array
     */
    public function getGreetingData(): array
    {
        // Get random categories from database
        $categories = Category::inRandomOrder()
            ->limit(5)
            ->get(['id', 'name', 'description']);

        // Get popular articles for each category
        $categoryArticles = [];
        foreach ($categories as $category) {
            $articles = Article::where('category_id', $category->id)
                ->where('is_published', true)
                ->orderBy('views', 'desc')
                ->limit(3)
                ->get(['id', 'title', 'slug']);
            
            $categoryArticles[$category->id] = $articles;
        }

        return [
            'greeting' => "Halo! 👋\nSaya SiMinfo.\nAda masalah apa hari ini?",
            'categories' => $categories->map(fn($cat) => [
                'id' => $cat->id,
                'label' => $cat->name,
                'description' => $cat->description,
                'articles' => $categoryArticles[$cat->id] ?? [],
            ]),
        ];
    }

    /**
     * Get subtopics for a category
     * 
     * @param string $categoryId
     * @return array
     */
    public function getCategorySubtopics(string $categoryId): array
    {
        $category = Category::find($categoryId);
        if (!$category) {
            return ['error' => 'Kategori tidak ditemukan'];
        }

        // Get common issues from article titles in this category
        $articles = Article::where('category_id', $categoryId)
            ->where('is_published', true)
            ->orderBy('views', 'desc')
            ->limit(8)
            ->get(['id', 'title', 'slug']);

        // Extract common problem patterns from titles
        $subtopics = [];
        foreach ($articles as $article) {
            // Extract key phrases from title
            $title = $article->title;
            
            // Simple extraction: take first part before "dengan", "saat", "ketika", etc.
            $patterns = ['/^(.+?)\s+dengan/i', '/^(.+?)\s+saat/i', '/^(.+?)\s+ketika/i', '/^(.+?)\s+pada/i'];
            $extracted = $title;
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $title, $matches)) {
                    $extracted = $matches[1];
                    break;
                }
            }

            // Only add if it's meaningful (more than 3 characters)
            if (strlen($extracted) > 3 && !in_array($extracted, array_column($subtopics, 'label'))) {
                $subtopics[] = [
                    'id' => $article->id,
                    'label' => $extracted,
                    'full_title' => $title,
                    'slug' => $article->slug,
                ];
            }
        }

        // Limit to 6 subtopics
        $subtopics = array_slice($subtopics, 0, 6);

        return [
            'category' => $category->name,
            'question' => "{$category->name} kamu sedang bermasalah apa? 😊",
            'subtopics' => $subtopics,
        ];
    }

    /**
     * Check if a query contains both domain and issue terms
     * If yes, it's contextual and should skip clarification
     * 
     * @param string $query
     * @return bool
     */
    private function isContextualQuery(string $query): bool
    {
        $hasDomain = false;
        $hasIssue = false;

        // Check for domain terms
        foreach ($this->domainTerms as $term) {
            if (strpos($query, $term) !== false) {
                $hasDomain = true;
                break;
            }
        }

        // Check for issue terms
        foreach ($this->issueTerms as $term) {
            if (strpos($query, $term) !== false) {
                $hasIssue = true;
                break;
            }
        }

        return $hasDomain && $hasIssue;
    }

    /**
     * Check if a query is ambiguous and needs clarification
     * 
     * @param string $query
     * @return array
     */
    public function checkAmbiguity(string $query): array
    {
        $query = strtolower(trim($query));

        // BUG FIX 1: Skip ambiguity for contextual queries (domain + issue)
        // Example: "wifi lemot" has both domain (wifi) and issue (lemot)
        // These should go directly to retrieval, not clarification
        if ($this->isContextualQuery($query)) {
            return ['is_ambiguous' => false];
        }

        // BUG FIX 1: Only trigger for standalone ambiguous patterns
        // The pattern must be the ENTIRE query (or very close to it)
        foreach ($this->ambiguousPatterns as $pattern) {
            if (strpos($query, $pattern) !== false) {
                // If the pattern is the query itself (standalone word), it's ambiguous
                // But if there are other significant words, it's contextual
                $patternPos = strpos($query, $pattern);
                $beforePattern = trim(substr($query, 0, $patternPos));
                $afterPattern = trim(substr($query, $patternPos + strlen($pattern)));

                // Count significant words (more than 2 chars) outside the pattern
                $extraWords = 0;
                if (strlen($beforePattern) > 2) $extraWords++;
                if (strlen($afterPattern) > 2) $extraWords++;

                // If there are extra significant words, it's contextual, not ambiguous
                if ($extraWords > 0) {
                    return ['is_ambiguous' => false];
                }

                return [
                    'is_ambiguous' => true,
                    'query' => $query,
                    'clarification' => $this->getClarificationForQuery($query),
                ];
            }
        }

        // Only flag very short single words as ambiguous
        if (strlen($query) < 5 && preg_match('/^[a-z]+$/', $query)) {
            return [
                'is_ambiguous' => true,
                'query' => $query,
                'clarification' => [
                    'question' => 'Bisa lebih spesifik? 😊',
                    'suggestions' => $this->getCategorySuggestions(),
                ],
            ];
        }

        return ['is_ambiguous' => false];
    }

    /**
     * Get clarification question and suggestions for ambiguous query
     * 
     * @param string $query
     * @return array
     */
    private function getClarificationForQuery(string $query): array
    {
        // Map query to category suggestions
        $categoryMap = [
            'lemot' => 'Yang sedang lemot apa ya? 😊',
            'lambat' => 'Yang sedang lambat apa ya? 😊',
            'error' => 'Error di bagian mana? 😊',
            'eror' => 'Error di bagian mana? 😊',
            'tidak bisa' => 'Tidak bisa apa? 😊',
            'gak bisa' => 'Gak bisa apa? 😊',
            'ga bisa' => 'Gak bisa apa? 😊',
            'bermasalah' => 'Bermasalah di bagian mana? 😊',
            'masalah' => 'Masalah di bagian mana? 😊',
            'rusak' => 'Yang rusak apa? 😊',
            'mati' => 'Yang mati apa? 😊',
            'blank' => 'Yang blank apa? 😊',
            'kosong' => 'Yang kosong apa? 😊',
        ];

        $question = 'Bisa lebih spesifik? 😊';
        foreach ($categoryMap as $keyword => $q) {
            if (strpos($query, $keyword) !== false) {
                $question = $q;
                break;
            }
        }

        return [
            'question' => $question,
            'suggestions' => $this->getCategorySuggestions(),
        ];
    }

    /**
     * Get category suggestions for clarification
     * Returns deduplicated categories (BUG FIX 5)
     * 
     * @return array
     */
    private function getCategorySuggestions(): array
    {
        $categories = Category::inRandomOrder()
            ->limit(4)
            ->get(['id', 'name']);

        // BUG FIX 5: Deduplicate by name
        $seen = [];
        $unique = [];
        foreach ($categories as $cat) {
            $normalizedName = strtolower(trim($cat->name));
            if (!isset($seen[$normalizedName])) {
                $seen[$normalizedName] = true;
                $unique[] = [
                    'id' => $cat->id,
                    'label' => $cat->name,
                    'type' => 'category',
                ];
            }
        }

        return $unique;
    }

    /**
     * Store conversation context in session
     * 
     * @param string $context
     * @param mixed $data
     * @return void
     */
    public function storeContext(string $context, mixed $data): void
    {
        $conversationHistory = Session::get('chatbot_conversation', []);
        
        $conversationHistory[] = [
            'context' => $context,
            'data' => $data,
            'timestamp' => now()->timestamp,
        ];

        // Keep only last 5 interactions to avoid session bloat
        $conversationHistory = array_slice($conversationHistory, -5);
        
        Session::put('chatbot_conversation', $conversationHistory);
    }

    /**
     * Get current conversation context
     * 
     * @return array|null
     */
    public function getCurrentContext(): ?array
    {
        $conversationHistory = Session::get('chatbot_conversation', []);
        
        if (empty($conversationHistory)) {
            return null;
        }

        // Return most recent context
        return end($conversationHistory);
    }

    /**
     * Clear conversation context
     * 
     * @return void
     */
    public function clearContext(): void
    {
        Session::forget('chatbot_conversation');
    }

    /**
     * Get search suggestions based on partial query
     * 
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function getSearchSuggestions(string $query, int $limit = 5): array
    {
        $query = trim(strtolower($query));
        
        if (strlen($query) < 2) {
            return [];
        }

        // Search articles by title
        $articles = Article::where('is_published', true)
            ->where('title', 'LIKE', "%{$query}%")
            ->orderBy('views', 'desc')
            ->limit($limit)
            ->get(['id', 'title', 'slug']);

        return $articles->map(fn($article) => [
            'id' => $article->id,
            'label' => $article->title,
            'slug' => $article->slug,
            'type' => 'article',
        ])->toArray();
    }

    /**
     * Refine retrieval based on conversation context
     * 
     * @param string $query
     * @param array $context
     * @return string
     */
    public function refineQuery(string $query, array $context): string
    {
        // If previous context was a category, add it to query
        if (isset($context['data']['category_id'])) {
            $category = Category::find($context['data']['category_id']);
            if ($category) {
                return "{$category->name} {$query}";
            }
        }

        // If previous context was a subtopic, use that
        if (isset($context['data']['subtopic'])) {
            return "{$context['data']['subtopic']} {$query}";
        }

        return $query;
    }

    /**
     * Get related articles for a given article
     * 
     * @param int $articleId
     * @param int $limit
     * @return array
     */
    public function getRelatedArticles(int $articleId, int $limit = 3): array
    {
        $article = Article::find($articleId);
        if (!$article) {
            return [];
        }

        // Get articles in same category, excluding current
        $related = Article::where('category_id', $article->category_id)
            ->where('id', '!=', $articleId)
            ->where('is_published', true)
            ->orderBy('views', 'desc')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'excerpt']);

        return $related->map(fn($art) => [
            'id' => $art->id,
            'title' => $art->title,
            'slug' => $art->slug,
            'excerpt' => $art->excerpt,
            'category_name' => $article->category->name,
        ])->toArray();
    }
}