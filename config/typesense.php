<?php

return [
    /*
     * Typesense server configuration
     */
    'host' => env('TYPESENSE_HOST', 'localhost'),
    'port' => env('TYPESENSE_PORT', 8108),
    'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
    'api_key' => env('TYPESENSE_API_KEY', 'xyz'),

    /*
     * Collection name for articles
     */
    'collections' => [
        'articles' => [
            'name' => 'articles',
            'fields' => [
                ['name' => 'id', 'type' => 'string', 'facet' => false],
                // Enable infix search on all text fields for maximum typo tolerance
                // This allows matching typos anywhere within words (e.g., "doocker" -> "docker")
                ['name' => 'title', 'type' => 'string', 'facet' => false, 'infix' => true],
                ['name' => 'content', 'type' => 'string', 'facet' => false, 'infix' => true],
                ['name' => 'excerpt', 'type' => 'string', 'facet' => false, 'infix' => true],
                ['name' => 'keywords', 'type' => 'string', 'facet' => false, 'infix' => true],
                ['name' => 'category_name', 'type' => 'string', 'facet' => true, 'infix' => true],
                ['name' => 'category_id', 'type' => 'string', 'facet' => true],
                ['name' => 'slug', 'type' => 'string', 'facet' => false],
                ['name' => 'is_published', 'type' => 'bool', 'facet' => false],
                ['name' => 'views', 'type' => 'int32', 'facet' => false],
                ['name' => 'created_at', 'type' => 'int64', 'facet' => false],
            ],
            'default_sorting_field' => 'views',
            'token_separators' => [' ', '-'],
            'symbols_to_index' => ['+', '#'],
        ],
    ],

    /*
     * Search configuration defaults
     */
    'search' => [
        'per_page' => 10,
        'num_typos' => 2,           // Allow up to 2 typos
        'min_len_1typo' => 4,       // Minimum length for 1 typo
        'min_len_2typo' => 8,       // Minimum length for 2 typos
        'prefix' => 'always',       // Enable prefix search
        'drop_tokens_threshold' => 3, // Drop tokens if too many results
        'typo_tokens_threshold' => 3, // Try without typos if too many results
        'exhaustive_search' => false,
    ],
];