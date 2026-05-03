<?php

require_once 'vendor/autoload.php';

use App\Models\Article;

$articles = Article::with('category')->where('is_published', true)->where('publish_status', 'approved')->get();

echo "Jumlah artikel: " . $articles->count() . "\n";

foreach ($articles as $article) {
    echo "Title: " . $article->title . "\n";
    echo "Category: " . ($article->category->name ?? 'No category') . "\n";
}