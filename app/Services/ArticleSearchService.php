<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleKeywordIndex;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class ArticleSearchService
{
    private const IDF_CACHE_KEY = 'chatbot:idf_scores';
    private const IDF_CACHE_TTL = 86400; // 24 jam

    public function __construct()
    {
        // TF-IDF Scoring enabled
    }

    /**
     * Main search method — TF-IDF dengan fallback
     */
    public function search(string $query, int $limit = 5): Collection
    {
        $normalizedQuery = $this->normalizeQuery($query);

        // Coba TF-IDF search terlebih dahulu
        try {
            $results = $this->searchTfIdf($normalizedQuery, $limit);
            if ($results->isNotEmpty()) {
                return $results;
            }
        } catch (Exception $e) {
            Log::warning('TF-IDF search failed, falling back', [
                'error' => $e->getMessage(),
                'query' => $normalizedQuery
            ]);
        }

        // Fallback ke FULLTEXT
        try {
            $results = $this->searchFulltext($normalizedQuery, $limit);
            if ($results->isNotEmpty()) {
                return $results;
            }
        } catch (Exception $e) {
            Log::warning('FULLTEXT search failed, falling back to LIKE', [
                'error' => $e->getMessage(),
                'query' => $normalizedQuery
            ]);
        }

        // Last resort: LIKE search
        return $this->searchLike($normalizedQuery, $limit);
    }

    /**
     * Auto-index artikel saat dipublikasikan
     */
    public function indexArticle(Article $article): void
    {
        if (!$article->is_published) {
            return;
        }

        try {
            // Ekstrak teks dari title, excerpt, keywords, content
            $titleTerms = $this->extractTerms($article->title);
            $excerptTerms = $this->extractTerms($article->excerpt ?? '');
            $keywordsTerms = $this->extractTerms($article->keywords ?? '');
            $contentTerms = $this->extractTerms($article->content);

            // Hitung TF untuk setiap field
            $titleTf = $this->calculateTermFrequency($titleTerms);
            $excerptTf = $this->calculateTermFrequency($excerptTerms);
            $keywordsTf = $this->calculateTermFrequency($keywordsTerms);
            $contentTf = $this->calculateTermFrequency($contentTerms);

            // Gabung dengan bobot field
            $allTerms = array_unique(array_merge(array_keys($titleTf), array_keys($excerptTf), array_keys($keywordsTf), array_keys($contentTf)));

            // Hapus index lama
            ArticleKeywordIndex::where('article_id', $article->id)->delete();

            // Insert index baru
            $records = [];
            foreach ($allTerms as $term) {
                $tfTitle = $titleTf[$term] ?? 0;
                $tfExcerpt = $excerptTf[$term] ?? 0;
                $tfKeywords = $keywordsTf[$term] ?? 0;
                $tfContent = $contentTf[$term] ?? 0;

                // TF total dengan bobot (title > excerpt/keywords > content)
                $tfTotal = ($tfTitle * 3) + ($tfExcerpt * 2) + ($tfKeywords * 2) + ($tfContent * 1);

                $records[] = [
                    'article_id' => $article->id,
                    'keyword' => $term,
                    'tf' => $tfTotal,
                    'field_boosts' => json_encode([
                        'title' => $tfTitle,
                        'excerpt' => $tfExcerpt,
                        'keywords' => $tfKeywords,
                        'content' => $tfContent,
                    ]),
                ];
            }

            if (!empty($records)) {
                ArticleKeywordIndex::insert($records);
            }

            // Bust IDF cache
            Cache::forget(self::IDF_CACHE_KEY);

            Log::info('Article indexed successfully', [
                'article_id' => $article->id,
                'keywords_count' => count($allTerms),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to index article', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Hapus index artikel
     */
    public function removeArticleIndex(int $articleId): void
    {
        try {
            ArticleKeywordIndex::where('article_id', $articleId)->delete();
            Cache::forget(self::IDF_CACHE_KEY);

            Log::info('Article index removed', ['article_id' => $articleId]);
        } catch (Exception $e) {
            Log::error('Failed to remove article index', [
                'article_id' => $articleId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * TF-IDF Search dengan smart scoring
     */
    private function searchTfIdf(string $query, int $limit): Collection
    {
        $queryTerms = $this->extractTerms($query);

        if (empty($queryTerms)) {
            return collect();
        }

        // Hitung IDF scores
        $idfScores = $this->calculateIdfScores($queryTerms);

        // Cari artikel yang cocok
        $indexRecords = ArticleKeywordIndex::whereIn('keyword', $queryTerms)
            ->with('article')
            ->get()
            ->groupBy('article_id');

        if ($indexRecords->isEmpty()) {
            return collect();
        }

        // Hitung skor per artikel
        $articleScores = [];
        foreach ($indexRecords as $articleId => $records) {
            $tfidfScore = 0;
            $matchedTerms = 0;

            foreach ($records as $record) {
                $keyword = $record->keyword;
                $tf = $record->tf;
                $idf = $idfScores[$keyword] ?? 0;

                $tfidfScore += $tf * $idf;
                $matchedTerms++;
            }

            // Coverage bonus: artikel yang match lebih banyak term mendapat bonus
            $coverageBonus = ($matchedTerms / count($queryTerms)) * 0.2;
            $tfidfScore += $tfidfScore * $coverageBonus;

            $articleScores[$articleId] = [
                'score' => $tfidfScore,
                'matched_terms' => $matchedTerms,
            ];
        }

        // Sort by score
        arsort($articleScores);
        $topArticleIds = array_slice(array_keys($articleScores), 0, $limit);

        if (empty($topArticleIds)) {
            return collect();
        }

        return Article::with('category')
            ->whereIn('id', $topArticleIds)
            ->where('is_published', true)
            ->where('publish_status', 'approved')
            ->orderByRaw('FIELD(id, ' . implode(',', $topArticleIds) . ')')
            ->get();
    }

    /**
     * Ekstrak term dari teks dan stem
     */
    private function extractTerms(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        $words = explode(' ', trim($text));
        $stopwords = $this->getStopwords();

        $terms = [];
        foreach ($words as $word) {
            if (mb_strlen($word) > 2 && !in_array($word, $stopwords)) {
                // Stem kata
                $stemmed = $this->stem($word);
                if (mb_strlen($stemmed) > 2) {
                    $terms[$stemmed] = ($terms[$stemmed] ?? 0) + 1;
                }
            }
        }

        return $terms;
    }

    /**
     * Hitung Term Frequency
     */
    private function calculateTermFrequency(array $terms): array
    {
        $totalTerms = array_sum($terms);

        if ($totalTerms === 0) {
            return [];
        }

        $tf = [];
        foreach ($terms as $term => $count) {
            $tf[$term] = $count / $totalTerms;
        }

        return $tf;
    }

    /**
     * Hitung IDF Scores untuk query terms
     */
    private function calculateIdfScores(array $queryTerms): array
    {
        $cached = Cache::get(self::IDF_CACHE_KEY);
        if ($cached !== null) {
            $allIdf = $cached;
        } else {
            $allIdf = $this->computeAllIdf();
            Cache::put(self::IDF_CACHE_KEY, $allIdf, self::IDF_CACHE_TTL);
        }

        $idfScores = [];
        foreach ($queryTerms as $term => $count) {
            $idfScores[$term] = $allIdf[$term] ?? log(2); // default IDF jika term tidak ditemukan
        }

        return $idfScores;
    }

    /**
     * Hitung IDF untuk semua term yang ada di index
     */
    private function computeAllIdf(): array
    {
        $totalArticles = Article::where('is_published', true)->where('publish_status', 'approved')->count();

        if ($totalArticles === 0) {
            return [];
        }

        $idfScores = [];
        $distinctKeywords = ArticleKeywordIndex::distinct('keyword')
            ->pluck('keyword');

        foreach ($distinctKeywords as $keyword) {
            $documentsWithKeyword = ArticleKeywordIndex::where('keyword', $keyword)
                ->distinct('article_id')
                ->count();

            // IDF = log(total_documents / documents_with_term)
            $idfScores[$keyword] = $documentsWithKeyword > 0
                ? log($totalArticles / $documentsWithKeyword)
                : log($totalArticles);
        }

        return $idfScores;
    }

    /**
     * Sederhana stemmer bahasa Indonesia
     */
    private function stem(string $word): string
    {
        // List prefix umum
        $prefixes = ['me', 'di', 'ter', 'ke', 'be', 'pe'];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($word, $prefix) && mb_strlen($word) > mb_strlen($prefix) + 2) {
                $word = mb_substr($word, mb_strlen($prefix));
            }
        }

        // List suffix umum
        $suffixes = ['kan', 'an', 'i', 'nya', 'lah', 'tah'];
        foreach ($suffixes as $suffix) {
            if (str_ends_with($word, $suffix) && mb_strlen($word) > mb_strlen($suffix) + 2) {
                $word = mb_substr($word, 0, mb_strlen($word) - mb_strlen($suffix));
            }
        }

        return $word;
    }

    /**
     * Normalisasi query: lowercase, hapus simbol, hapus stopwords
     */
    private function normalizeQuery(string $query): string
    {
        $query = mb_strtolower($query);
        $query = preg_replace('/[^a-z0-9\s]/', ' ', $query);
        $query = preg_replace('/\s+/', ' ', $query);
        $query = $this->removeStopwords(trim($query));
        return trim($query);
    }

    /**
     * Dapatkan stopwords bahasa Indonesia
     */
    private function getStopwords(): array
    {
        return [
            'saya', 'aku', 'kamu', 'anda', 'tolong', 'dong', 'ini', 'itu',
            'yang', 'di', 'ke', 'dari', 'untuk', 'dengan', 'pada', 'oleh',
            'adalah', 'dan', 'atau', 'tapi', 'karena', 'jika', 'maka',
            'lalu', 'kemudian', 'setelah', 'sebelum', 'bagaimana', 'apa',
            'dimana', 'kapan', 'kenapa', 'siapa', 'berapa', 'ada', 'tidak',
            'bisa', 'sudah', 'belum', 'sedang', 'akan', 'ingin', 'mau',
        ];
    }

    /**
     * Hapus stopwords dari teks
     */
    private function removeStopwords(string $text): string
    {
        $stopwords = $this->getStopwords();
        $words = explode(' ', $text);
        $filtered = array_filter($words, fn($w) => !in_array($w, $stopwords) && mb_strlen($w) > 1);

        return implode(' ', $filtered);
    }


    /**
     * Search menggunakan MySQL FULLTEXT (TF-IDF ranking)
     */
    private function searchFulltext(string $query, int $limit): Collection
    {
        $keywords = explode(' ', $query);
        $searchTerm = implode('* ', $keywords) . '*'; // wildcard untuk partial match

        $results = DB::select("
            SELECT id, title, content, category_id, views,
                   MATCH(title, content) AGAINST(? IN BOOLEAN MODE) AS score
            FROM articles
            WHERE is_published = 1
              AND MATCH(title, content) AGAINST(? IN BOOLEAN MODE)
            ORDER BY score DESC, views DESC
            LIMIT ?
        ", [$searchTerm, $searchTerm, $limit]);

        $articleIds = collect($results)->pluck('id')->toArray();

        if (empty($articleIds)) {
            return collect();
        }

        return Article::with('category')
            ->whereIn('id', $articleIds)
            ->where('is_published', true)
            ->where('publish_status', 'approved')
            ->orderByRaw('FIELD(id, ' . implode(',', $articleIds) . ')')
            ->get();
    }

    /**
     * Fallback search menggunakan LIKE (slow, but works)
     */
    private function searchLike(string $query, int $limit): Collection
    {
        $keywords = array_filter(explode(' ', $query), fn($k) => mb_strlen($k) > 2);

        if (empty($keywords)) {
            return collect();
        }

        return Article::with('category')
            ->where('is_published', true)
            ->where('publish_status', 'approved')
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhere('title', 'LIKE', "%{$kw}%")
                      ->orWhere('excerpt', 'LIKE', "%{$kw}%")
                      ->orWhere('keywords', 'LIKE', "%{$kw}%")
                      ->orWhere('content', 'LIKE', "%{$kw}%");
                }
            })
            ->orderByDesc('views')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}