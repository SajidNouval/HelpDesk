<?php

namespace App\Services\Chatbot;

use App\Models\Article;
use Exception;
use Illuminate\Support\Facades\Log;
use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;

class TypesenseService
{
    private ?Client $client = null;
    private bool $isConnected = false;
    private array $debugInfo = [];

    /**
     * Intent-level synonym sets for improving query understanding.
     * Each set groups related terms that express the same intent.
     * These are SMALL and INTENT-FOCUSED, not giant manual dictionaries.
     */
    private array $intentSynonymSets = [
        // Connectivity intent: terms related to connecting/network connectivity
        'connectivity' => [
            'connect', 'konek', 'terhubung', 'tersambung', 'online',
            'connection', 'koneksi', 'sambung', 'nyambung'
        ],

        // Security intent: terms related to security threats
        'security' => [
            'virus', 'malware', 'trojan', 'ransomware',
            'spyware', 'adware', 'worm', 'phishing'
        ],

        // Printing intent: terms related to printing
        'printing' => [
            'print', 'printer', 'cetak', 'ngeprint',
            'printing', 'mencetak', 'percetakan'
        ],

        // Authentication intent: terms related to login/account access
        'authentication' => [
            'login', 'signin', 'sign-in', 'masuk akun',
            'log in', 'log-in', 'masuk', 'sign up', 'signup',
            'register', 'daftar'
        ],

        // Network/internet intent: terms related to network issues
        'network' => [
            'wifi', 'internet', 'jaringan', 'network',
            'lan', 'wireless', 'nirkabel', 'router',
            'modem', 'access point', 'hotspot'
        ],

        // Error/failure intent: terms expressing failure
        'failure' => [
            'gagal', 'error', 'gagal konek', 'tidak bisa',
            'ga bisa', 'gak bisa', 'tidak bisa', 'tidak connect',
            'tidak terhubung', 'masalah', 'issue', 'kendala'
        ],

        // Speed/performance intent: terms related to speed issues
        'speed' => [
            'lambat', 'slow', 'lemot', 'speed', 'kecepatan',
            'bandwidth', 'lag', 'lagging', 'buffering'
        ],

        // Email intent: terms related to email
        'email' => [
            'email', 'surel', 'mail', 'surat elektronik',
            'gmail', 'outlook', 'yahoo mail'
        ],
    ];

    /**
     * Initialize Typesense client
     */
    public function __construct()
    {
        $this->initializeClient();
    }

    /**
     * Initialize the Typesense client
     */
    private function initializeClient(): void
    {
        try {

            $this->client = new Client([
                'nodes' => [
                    [
                        'host' => config('typesense.host'),
                        'port' => config('typesense.port'),
                        'protocol' => config('typesense.protocol'),
                    ],
                ],
                'api_key' => config('typesense.api_key'),
                'connection_timeout_seconds' => 15,
            ]);

            // test koneksi
            $this->client->collections->retrieve();

            $this->isConnected = true;

        } catch (Exception $e) {

            $this->isConnected = false;

            logger()->error('Typesense connection failed', [
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if Typesense is connected
     */
    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    /**
     * Get the Typesense client
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    /**
     * Create or update the articles collection
     */
    public function createOrUpdateCollection(): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        try {
            $collectionConfig = config('typesense.collections.articles');
            $collectionName = $collectionConfig['name'];

            // Check if collection exists
            try {
                $existingCollection = $this->client->collections[$collectionName]->retrieve();
                
                // Delete and recreate to ensure schema is up to date
                $this->client->collections[$collectionName]->delete();
                Log::info("Deleted existing Typesense collection: {$collectionName}");
            } catch (ObjectNotFound $e) {
                Log::info("Creating new Typesense collection: {$collectionName}");
            }

            // Create the collection
            $collection = $this->client->collections->create([
                'name' => $collectionName,
                'fields' => $collectionConfig['fields'],
                'default_sorting_field' => $collectionConfig['default_sorting_field'],
                'token_separators' => $collectionConfig['token_separators'] ?? [],
                'symbols_to_index' => $collectionConfig['symbols_to_index'] ?? [],
            ]);

            Log::info("Typesense collection created: {$collectionName}", [
                'fields' => count($collectionConfig['fields']),
            ]);

            return [
                'success' => true,
                'message' => "Collection '{$collectionName}' created successfully",
                'collection' => $collection,
            ];
        } catch (Exception $e) {
            Log::error('Failed to create Typesense collection: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Index a single article
     */
    public function indexArticle(Article $article): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        try {
            $collectionName = config('typesense.collections.articles.name');
            
            $document = [
                'id' => (string) $article->id,
                'title' => $article->title,
                'content' => $article->content,
                'excerpt' => $article->excerpt ?? '',
                'keywords' => $article->keywords ?? '',
                'category_name' => $article->category?->name ?? '',
                'category_id' => (string) $article->category_id,
                'slug' => $article->slug,
                'is_published' => $article->is_published,
                'views' => $article->views ?? 0,
                'created_at' => $article->created_at?->timestamp ?? time(),
            ];

            $result = $this->client->collections[$collectionName]->documents->upsert($document);

            Log::debug("Indexed article to Typesense: {$article->id}", [
                'title' => $article->title,
                'is_published' => $article->is_published,
            ]);

            return [
                'success' => true,
                'message' => "Article '{$article->id}' indexed successfully",
                'document' => $result,
            ];
        } catch (Exception $e) {
            Log::error("Failed to index article {$article->id} to Typesense: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove an article from the index
     */
    public function removeArticle(string $articleId): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        try {
            $collectionName = config('typesense.collections.articles.name');
            
            $this->client->collections[$collectionName]->documents[$articleId]->delete();

            Log::debug("Removed article from Typesense: {$articleId}");

            return [
                'success' => true,
                'message' => "Article '{$articleId}' removed successfully",
            ];
        } catch (ObjectNotFound $e) {
            // Document doesn't exist, which is fine
            return [
                'success' => true,
                'message' => "Article '{$articleId}' was not in index",
            ];
        } catch (Exception $e) {
            Log::error("Failed to remove article {$articleId} from Typesense: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Security-related keywords for category boosting
     */
    private array $securityKeywords = [
        'virus', 'viruss', 'viruses', 'malware', 'ransomware', 'ransomwre',
        'trojan', 'trojans', 'phishing', 'spyware', 'adware', 'worm',
        'security', 'keamanan', 'antivirus', 'anti-virus',
    ];

    /**
     * Check if a query is security-related
     */
    private function isSecurityQuery(string $query): bool
    {
        $lowerQuery = strtolower($query);
        foreach ($this->securityKeywords as $keyword) {
            if (str_contains($lowerQuery, $keyword)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Normalize repeated characters in query tokens
     * Handles extreme typos like "vvvvvirus" -> "vvvirus"
     * 
     * @param string $query The query to normalize
     * @return string The normalized query
     */
    private function normalizeRepeatedChars(string $query): string
    {
        $tokens = explode(' ', $query);
        $normalizedTokens = [];
        
        foreach ($tokens as $token) {
            // Compress any character repeated 3+ times to 2 occurrences
            // This handles cases like "vvvvvirus" -> "vvvirus"
            $pattern = '/(.)\1{2,}/';
            $normalized = preg_replace($pattern, '$1$1', $token);
            $normalizedTokens[] = $normalized ?? $token;
        }
        
        return implode(' ', $normalizedTokens);
    }

    /**
     * Search articles with typo tolerance and fuzzy matching
     * This is the main retrieval method for the chatbot
     */
    public function search(string $query, int $limit = 20, array $options = []): array
    {
        $originalQuery = $query;
        
        // STEP 0: Normalize repeated characters before searching
        // This handles extreme typos like "vvvvvirus" -> "vvvirus"
        $normalizedQuery = $this->normalizeRepeatedChars($query);
        $query = $normalizedQuery;
        
        $this->debugInfo = [
            'original_query' => $originalQuery,
            'normalized_query' => $normalizedQuery,
            'limit' => $limit,
            'options' => $options,
            'candidates' => [],
            'typo_corrections' => [],
            'raw_hits_before_tfidf' => [],
            'security_boost_applied' => false,
        ];

        if (!$this->isConnected) {
            return [
                'success' => false,
                'message' => 'Typesense is not connected',
                'results' => [],
                'debug' => $this->debugInfo,
            ];
        }

        if (empty(trim($query))) {
            return [
                'success' => true,
                'message' => 'Empty query',
                'results' => [],
                'debug' => $this->debugInfo,
            ];
        }

        try {
            $collectionName = config('typesense.collections.articles.name');
            $searchConfig = config('typesense.search');

            // Check if this is a security-related query for boosting
            $isSecurityQuery = $this->isSecurityQuery($query);
            $this->debugInfo['is_security_query'] = $isSecurityQuery;

            // Build search parameters with improved ranking and typo tolerance
            $searchParams = [
                'q' => $query,
                'query_by' => 'title,keywords,category_name,content',  // Prioritize title, keywords, category
                'query_by_weights' => '8,6,4,2',         // Title=8, keywords=6, category=4, content=2
                
                // STEP 1: Improved search parameters for better ranking
                'prioritize_exact_match' => true,         // Prioritize exact matches
                'text_match_type' => 'max_score',         // Use max score for multi-field matching
                'token_separators' => [' ', '-'],         // Token separators for better word boundary detection
                'drop_tokens_threshold' => 0,             // Never drop tokens - keep all query terms
                
                // STEP 2: Enhanced typo tolerance with infix search
                // Allow up to 4 typos for very long words (handles extreme typos like "vvvvvirus")
                'num_typos' => 4,                         // Allow up to 4 typos (increased from 3)
                'min_len_1typo' => 2,                     // Minimum length for 1 typo (reduced from 3)
                'min_len_2typo' => 4,                     // Minimum length for 2 typos (reduced from 6)
                
                // Enable both prefix AND infix search for matching typos within words
                'prefix' => 'always',                     // Match at beginning of words
                'infix' => 'always',                      // Match anywhere within words (critical for typos like "doocker")
                'infix_score' => 'max_score',             // Use max score when infix matches
                
                'typo_tokens_threshold' => $searchConfig['typo_tokens_threshold'] ?? 3,
                
                'per_page' => $limit,
                'page' => 1,
                'exhaustive_search' => true,              // Enable exhaustive search for better typo matching
                'filter_by' => 'is_published:true',       // Only search published articles
            ];

            // Add category filter if specified
            if (isset($options['category_id'])) {
                $searchParams['filter_by'] .= ' && category_id:=' . $options['category_id'];
            }

            // Add domain filter if specified
            if (isset($options['domain'])) {
                $searchParams['filter_by'] .= ' && category_name:=' . $options['domain'];
            }

            // STEP 3: Boost security articles for security-related queries
            if ($isSecurityQuery) {
                // Use optional_filter_by to boost security category articles without excluding others
                $searchParams['optional_filter_by'] = 'category_name:=Keamanan Sistem';
                $this->debugInfo['security_boost_applied'] = true;
                $this->debugInfo['boost_category'] = 'Keamanan Sistem';
                
                Log::info('Security query detected - applying category boost', [
                    'query' => $query,
                    'boost_category' => 'Keamanan Sistem',
                ]);
            }

            // Execute search
            $searchResults = $this->client->collections[$collectionName]->documents->search($searchParams);

            // STEP 5: Log RAW Typesense hits BEFORE TF-IDF (critical for debugging)
            $rawHitsLog = [];
            if (isset($searchResults['hits']) && !empty($searchResults['hits'])) {
                foreach ($searchResults['hits'] as $idx => $hit) {
                    $document = $hit['document'];
                    $rawHitsLog[] = [
                        'rank' => $idx + 1,
                        'title' => $document['title'],
                        'typesense_score' => $hit['text_match'] ?? 0,
                        'category_name' => $document['category_name'] ?? '',
                    ];
                    
                    // Store in debug info for retrieval
                    $this->debugInfo['raw_hits_before_tfidf'][] = [
                        'rank' => $idx + 1,
                        'id' => $document['id'],
                        'title' => $document['title'],
                        'typesense_score' => $hit['text_match'] ?? 0,
                        'category_name' => $document['category_name'] ?? '',
                    ];
                }
            }

            Log::info('RAW Typesense results (before TF-IDF reranking)', [
                'query' => $query,
                'hits' => $rawHitsLog,
                'security_boost_applied' => $this->debugInfo['security_boost_applied'],
            ]);

            // Process results
            $results = [];
            if (isset($searchResults['hits']) && !empty($searchResults['hits'])) {
                foreach ($searchResults['hits'] as $hit) {
                    $document = $hit['document'];
                    
                    $results[] = [
                        'id' => $document['id'],
                        'title' => $document['title'],
                        'content' => $document['content'],
                        'excerpt' => $document['excerpt'] ?? '',
                        'keywords' => $document['keywords'] ?? '',
                        'category_name' => $document['category_name'] ?? '',
                        'category_id' => $document['category_id'] ?? '',
                        'slug' => $document['slug'],
                        'is_published' => $document['is_published'],
                        'views' => $document['views'] ?? 0,
                        'typesense_score' => $hit['text_match'] ?? 0,
                    ];

                    $this->debugInfo['candidates'][] = [
                        'id' => $document['id'],
                        'title' => $document['title'],
                        'score' => $hit['text_match'] ?? 0,
                        'category_name' => $document['category_name'] ?? '',
                    ];
                }
            }

            // Track typo corrections from search results
            if (isset($searchResults['request_params']['q'])) {
                $correctedQuery = $searchResults['request_params']['q'];
                if ($correctedQuery !== $query) {
                    $this->debugInfo['typo_corrections'] = [
                        'original' => $query,
                        'corrected' => $correctedQuery,
                    ];
                }
            }

            $this->debugInfo['total_found'] = $searchResults['found'] ?? 0;
            $this->debugInfo['search_time_ms'] = $searchResults['search_time_ms'] ?? 0;

            Log::info('Typesense search completed', [
                'query' => $query,
                'results_count' => count($results),
                'total_found' => $searchResults['found'] ?? 0,
                'search_time_ms' => $searchResults['search_time_ms'] ?? 0,
                'security_boost_applied' => $this->debugInfo['security_boost_applied'],
            ]);

            return [
                'success' => true,
                'message' => 'Search completed',
                'results' => $results,
                'total' => $searchResults['found'] ?? 0,
                'debug' => $this->debugInfo,
            ];
        } catch (Exception $e) {
            Log::error("Typesense search failed for query '{$query}': " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'results' => [],
                'debug' => $this->debugInfo,
            ];
        }
    }

    /**
     * Index all published articles (bulk indexing)
     */
    public function indexAllArticles(): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        $errorDetails = [];
        try {
            $articles = Article::where('is_published', true)
                ->with('category')
                ->get();

            $collectionName = config('typesense.collections.articles.name');
            $documents = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($articles as $article) {
                // Validasi minimal: id dan title wajib ada
                if (empty($article->id) || empty($article->title)) {
                    Log::warning('Article missing required fields', [
                        'id' => $article->id,
                        'title' => $article->title,
                    ]);
                    $errorCount++;
                    $errorDetails[] = [
                        'article_id' => $article->id,
                        'title' => $article->title,
                        'error' => 'Missing required fields',
                    ];
                    continue;
                }
                $documents[] = [
                    'id' => (string) $article->id,
                    'title' => $article->title,
                    'content' => $article->content,
                    'excerpt' => $article->excerpt ?? '',
                    'keywords' => $article->keywords ?? '',
                    'category_name' => $article->category?->name ?? '',
                    'category_id' => (string) $article->category_id,
                    'slug' => $article->slug,
                    'is_published' => $article->is_published,
                    'views' => $article->views ?? 0,
                    'created_at' => $article->created_at?->timestamp ?? time(),
                ];
            }

            if (count($documents) > 0) {
                // Upsert documents in batch
                $result = $this->client->collections[$collectionName]->documents->import($documents, ['action' => 'upsert']);

                foreach ($result as $idx => $item) {
                    if (isset($item['success']) && $item['success']) {
                        $successCount++;
                    } else {
                        $errorCount++;
                        $errorDetails[] = [
                            'article_id' => $documents[$idx]['id'] ?? null,
                            'title' => $documents[$idx]['title'] ?? null,
                            'error' => $item['error'] ?? 'Unknown error',
                        ];
                        Log::error('Bulk indexing error', [
                            'article_id' => $documents[$idx]['id'] ?? null,
                            'title' => $documents[$idx]['title'] ?? null,
                            'error' => $item['error'] ?? 'Unknown error',
                        ]);
                    }
                }
            }

            Log::info("Bulk indexed {$successCount} articles to Typesense", [
                'success' => $successCount,
                'errors' => $errorCount,
            ]);

            return [
                'success' => true,
                'message' => "Indexed {$successCount} articles, {$errorCount} errors",
                'indexed' => $successCount,
                'errors' => $errorCount,
                'error_details' => $errorDetails,
            ];
        } catch (Exception $e) {
            Log::error('Bulk indexing fatal error', [
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'indexed' => 0,
                'errors' => 1,
                'error_details' => [[
                    'error' => $e->getMessage(),
                ]],
            ];
        }
    }

    /**
     * Delete the articles collection
     */
    public function deleteCollection(): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        try {
            $collectionName = config('typesense.collections.articles.name');
            
            $this->client->collections[$collectionName]->delete();

            Log::info("Deleted Typesense collection: {$collectionName}");

            return [
                'success' => true,
                'message' => "Collection '{$collectionName}' deleted successfully",
            ];
        } catch (ObjectNotFound $e) {
            return [
                'success' => true,
                'message' => "Collection does not exist",
            ];
        } catch (Exception $e) {
            Log::error("Failed to delete Typesense collection: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get collection statistics
     */
    public function getCollectionStats(): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        try {
            $collectionName = config('typesense.collections.articles.name');
            
            $stats = $this->client->collections[$collectionName]->retrieve();

            return [
                'success' => true,
                'message' => 'Collection stats retrieved',
                'stats' => [
                    'name' => $stats['name'] ?? $collectionName,
                    'num_documents' => $stats['num_documents'] ?? 0,
                    'fields' => count($stats['fields'] ?? []),
                ],
            ];
        } catch (ObjectNotFound $e) {
            return [
                'success' => false,
                'message' => 'Collection does not exist',
            ];
        } catch (Exception $e) {
            Log::error("Failed to get Typesense collection stats: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get debug info from last operation
     */
    public function getDebugInfo(): array
    {
        return $this->debugInfo;
    }

    /**
     * Get the collection name for synonyms
     */
    private function getSynonymCollectionName(): string
    {
        return config('typesense.collections.articles.name');
    }

    /**
     * Get all intent synonym sets
     */
    public function getIntentSynonymSets(): array
    {
        return $this->intentSynonymSets;
    }

    /**
     * Create or update a single synonym set in Typesense
     * 
     * @param string $synonymId Unique identifier for the synonym set
     * @param array $synonyms Array of synonym terms
     * @return array Result of the operation
     */
    public function createSynonym(string $synonymId, array $synonyms): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        if (empty($synonyms) || count($synonyms) < 2) {
            return ['success' => false, 'message' => 'Synonym set must have at least 2 terms'];
        }

        try {
            $collectionName = $this->getSynonymCollectionName();
            
            $synonymConfig = [
                'id' => $synonymId,
                'synonyms' => $synonyms,
            ];

            $result = $this->client->collections[$collectionName]->synonyms->upsert($synonymId, $synonymConfig);

            Log::info("Created/updated Typesense synonym: {$synonymId}", [
                'terms' => count($synonyms),
            ]);

            return [
                'success' => true,
                'message' => "Synonym '{$synonymId}' created/updated successfully",
                'data' => $result,
            ];
        } catch (Exception $e) {
            Log::error("Failed to create Typesense synonym {$synonymId}: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create all intent synonym sets in Typesense
     * 
     * @return array Result of the bulk operation
     */
    public function createAllSynonyms(): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        $results = [
            'success' => true,
            'created' => 0,
            'errors' => 0,
            'details' => [],
        ];

        foreach ($this->intentSynonymSets as $intent => $synonyms) {
            $result = $this->createSynonym($intent, $synonyms);
            
            if ($result['success']) {
                $results['created']++;
            } else {
                $results['errors']++;
                $results['details'][] = [
                    'intent' => $intent,
                    'error' => $result['message'],
                ];
            }
        }

        Log::info("Created {$results['created']} synonym sets, {$results['errors']} errors");

        return $results;
    }

    /**
     * Get a specific synonym set from Typesense
     * 
     * @param string $synonymId The synonym set identifier
     * @return array|null The synonym data or null if not found
     */
    public function getSynonym(string $synonymId): ?array
    {
        if (!$this->isConnected) {
            return null;
        }

        try {
            $collectionName = $this->getSynonymCollectionName();
            $synonym = $this->client->collections[$collectionName]->synonyms[$synonymId]->retrieve();
            return $synonym;
        } catch (ObjectNotFound $e) {
            return null;
        } catch (Exception $e) {
            Log::error("Failed to get Typesense synonym {$synonymId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all synonym sets from Typesense
     * 
     * @return array Array of all synonym sets
     */
    public function getAllSynonyms(): array
    {
        if (!$this->isConnected) {
            return [];
        }

        try {
            $collectionName = $this->getSynonymCollectionName();
            $synonyms = $this->client->collections[$collectionName]->synonyms->retrieve();
            return $synonyms['synonyms'] ?? [];
        } catch (Exception $e) {
            Log::error("Failed to get all Typesense synonyms: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete a specific synonym set from Typesense
     * 
     * @param string $synonymId The synonym set identifier
     * @return array Result of the operation
     */
    public function deleteSynonym(string $synonymId): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        try {
            $collectionName = $this->getSynonymCollectionName();
            $this->client->collections[$collectionName]->synonyms[$synonymId]->delete();

            Log::info("Deleted Typesense synonym: {$synonymId}");

            return [
                'success' => true,
                'message' => "Synonym '{$synonymId}' deleted successfully",
            ];
        } catch (ObjectNotFound $e) {
            return [
                'success' => true,
                'message' => "Synonym '{$synonymId}' does not exist",
            ];
        } catch (Exception $e) {
            Log::error("Failed to delete Typesense synonym {$synonymId}: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete all synonym sets from Typesense
     * 
     * @return array Result of the operation
     */
    public function deleteAllSynonyms(): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        $deleted = 0;
        $errors = 0;

        foreach (array_keys($this->intentSynonymSets) as $intent) {
            $result = $this->deleteSynonym($intent);
            if ($result['success']) {
                $deleted++;
            } else {
                $errors++;
            }
        }

        Log::info("Deleted {$deleted} synonym sets, {$errors} errors");

        return [
            'success' => true,
            'deleted' => $deleted,
            'errors' => $errors,
        ];
    }

    /**
     * Check if a query matches any synonym intent
     * 
     * @param string $query The search query
     * @return array Array of matched intents with their synonym terms
     */
    public function matchSynonymIntents(string $query): array
    {
        $lowerQuery = strtolower(trim($query));
        $matchedIntents = [];

        foreach ($this->intentSynonymSets as $intent => $synonyms) {
            foreach ($synonyms as $synonym) {
                if (str_contains($lowerQuery, strtolower($synonym))) {
                    if (!isset($matchedIntents[$intent])) {
                        $matchedIntents[$intent] = [
                            'intent' => $intent,
                            'matched_terms' => [],
                            'all_synonyms' => $synonyms,
                        ];
                    }
                    if (!in_array($synonym, $matchedIntents[$intent]['matched_terms'])) {
                        $matchedIntents[$intent]['matched_terms'][] = $synonym;
                    }
                    break;
                }
            }
        }

        return $matchedIntents;
    }
}
