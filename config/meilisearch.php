<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Search Engine
    |--------------------------------------------------------------------------
    |
    | This option controls the default search connection that gets used while
    | using Laravel Scout. This connection is used by the "scout:index" and
    | "scout:reindex" commands when no explicit connection is specified.
    |
    */

    'driver' => env('SCOUT_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Index Prefix
    |--------------------------------------------------------------------------
    |
    | Here you may specify a prefix that will be applied to all search index
    | names used by Scout. This prefix may be useful if you have multiple
    | applications or environments sharing a single search infrastructure.
    |
    */

    'prefix' => env('SCOUT_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Queue Data Syncing
    |--------------------------------------------------------------------------
    |
    | This option allows you to control if the operations that sync models to
    | your search index (save, delete, etc.) are queued. When this is set to
    | "true" then all operations will be dispatched to the queue instead of
    | being run in "real time". You must have a queue worker running.
    |
    */

    'queue' => env('SCOUT_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | Database Transactions
    |--------------------------------------------------------------------------
    |
    | This configuration option determines if your Eloquent models will be
    | indexed within a database transaction. This ensures that when an
    | exception occurs, the search index is not left in an inconsistent
    | state. It's recommended to enable this in production environments.
    |
    */

    'after_commit' => env('SCOUT_AFTER_COMMIT', false),

    /*
    |--------------------------------------------------------------------------
    | Chunk Sizes
    |--------------------------------------------------------------------------
    |
    | These configuration options allow you to control the maximum chunk size
    | when you are mass importing data into your search engine. This allows
    | you to fine tune each of these chunk sizes based on the power of the
    | servers or the size of documents (default: 500 for all engines).
    |
    */

    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    |
    | This configuration option determines if Scout should remove soft deleted
    | records from the search index. When data is soft deleted, you will
    | not be able to search for it.
    |
    */

    'soft_delete' => env('SCOUT_SOFT_DELETE', false),

    /*
    |--------------------------------------------------------------------------
    | Identify User
    |--------------------------------------------------------------------------
    |
    | This configuration option allows you to control whether to identify the
    | user performing the search. This is useful if the search engine
    | supports this feature, which in turn allows search result metrics.
    |
    */

    'identify' => env('SCOUT_IDENTIFY', true),

    /*
    |--------------------------------------------------------------------------
    | MeiliSearch Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your MeiliSearch settings. MeiliSearch is an open
    | source search engine with minimal configuration. Generally, you will only
    | need to update the host and key settings for your MeiliSearch instance.
    |
    */

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY', null),
        'index-settings' => [
            'articles' => [
                'filterableAttributes' => ['is_published', 'category_name'],
                'sortableAttributes' => ['views', 'created_at'],
                'searchableAttributes' => ['title', 'content', 'category_name'],
                'displayedAttributes' => ['*'],
                'rankingRules' => [
                    'words',
                    'typo',
                    'proximity',
                    'attribute',
                    'sort',
                    'exactness',
                ],
                'stopWords' => [
                    'saya', 'aku', 'kamu', 'anda', 'tolong', 'dong', 'ini', 'itu',
                    'yang', 'di', 'ke', 'dari', 'untuk', 'dengan', 'pada', 'oleh',
                    'adalah', 'dan', 'atau', 'tapi', 'karena', 'jika', 'maka',
                    'lalu', 'kemudian', 'setelah', 'sebelum', 'bagaimana', 'apa',
                    'dimana', 'kapan', 'kenapa', 'siapa', 'berapa', 'ada', 'tidak',
                    'bisa', 'sudah', 'belum', 'sedang', 'akan', 'ingin', 'mau',
                ],
                'synonyms' => [
                    'login' => ['masuk', 'log in', 'signin'],
                    'password' => ['kata sandi', 'sandi', 'pass'],
                    'wifi' => ['wireless', 'internet', 'jaringan'],
                    'error' => ['kesalahan', 'galat', 'bug'],
                    'reset' => ['atur ulang', 'restart', 'ulang'],
                    'lupa' => ['lupakan', 'hilang', 'kehilangan'],
                    'tidak bisa' => ['gagal', 'error', 'bukan'],
                    'bagaimana' => ['cara', 'gimana', 'how'],
                    'apa' => ['what', 'kenapa', 'why'],
                ],
                'typoTolerance' => [
                    'enabled' => true,
                    'minWordSizeForTypos' => [
                        'oneTypo' => 5,
                        'twoTypos' => 10,
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Algolia Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Algolia settings. Algolia is a cloud-based
    | search engine with a powerful API. Generally, you will only need to
    | update the application ID and key settings for your Algolia account.
    |
    */

    'algolia' => [
        'id' => env('ALGOLIA_APP_ID', ''),
        'secret' => env('ALGOLIA_SECRET', ''),
    ],
];