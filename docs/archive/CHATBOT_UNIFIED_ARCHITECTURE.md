# TF-IDF Chatbot - Unified Architecture

## Overview

The TF-IDF chatbot has been fully unified into a single, consistent retrieval pipeline. All duplicate systems have been deprecated and removed.

## Architecture (Single Pipeline)

```
User Query
    ↓
[Input Validation] - Length check, sanitization
    ↓
[Greeting Detection] - Lightweight rule-based for greetings only
    ↓
[Preprocessing] - Case folding, cleaning, tokenization, stopword removal, stemming
    ↓
[TF-IDF Vectorization] - Calculate term frequency with field weights
    ↓
[Cosine Similarity] - Compare query vector with all document vectors
    ↓
[Title Boost] - Apply bonus if query terms match document title
    ↓
[Ranking] - Sort by boosted similarity, filter by threshold (0.05)
    ↓
[Top 3 Results] - Return highest-ranked articles
    ↓
[Response Generation] - Generate natural language response with confidence
    ↓
JSON Response
```

## Key Components

### 1. ChatbotRetrievalService (Single Source of Truth)
**Location:** `app/Services/Chatbot/ChatbotRetrievalService.php`

**Responsibilities:**
- Main TF-IDF retrieval engine
- Title boosting calculation
- Cosine similarity ranking
- Response formatting
- Dynamic topics and subtopics
- Cache management

**Key Methods:**
- `retrieve(query, limit)` - Main retrieval method
- `formatResponse(result)` - Format for chatbot display
- `rebuildCache()` - Rebuild TF-IDF vectors
- `clearCache()` - Clear all caches
- `getDynamicTopics()` - Get topic suggestions
- `getSubtopics()` - Get subtopic suggestions

### 2. PreprocessingService (Unified Preprocessing)
**Location:** `app/Services/Chatbot/PreprocessingService.php`

**Responsibilities:**
- Text normalization
- Tokenization
- Stopword removal (150+ Indonesian stopwords)
- Stemming (Indonesian language)
- Caching support

**Key Methods:**
- `preprocess(text)` - Full preprocessing pipeline
- `preprocessDocument(text)` - Returns tokens + frequency
- `preprocessWithCache(text, cacheKey)` - With caching

### 3. TfidfService (TF-IDF Calculations)
**Location:** `app/Services/Chatbot/TfidfService.php`

**Responsibilities:**
- TF (Term Frequency) calculation
- IDF (Inverse Document Frequency) calculation
- TF-IDF vector construction
- Vector normalization

### 4. CosineSimilarityService (Similarity Calculation)
**Location:** `app/Services/Chatbot/CosineSimilarityService.php`

**Responsibilities:**
- Cosine similarity calculation
- Batch similarity calculation
- Document ranking
- Threshold checking

### 5. ChatbotController (Thin Controller)
**Location:** `app/Http/Controllers/ChatbotController.php`

**Responsibilities:**
- Input validation
- Greeting detection delegation
- Service delegation
- JSON response formatting
- Debug logging

**NO retrieval logic** - all delegated to ChatbotRetrievalService

## Deprecated Components

### ArticleSearchService (DEPRECATED)
**Location:** `app/Services/ArticleSearchService.php`

**Status:** Deprecated - returns empty results
**Reason:** Was part of dual TF-IDF system causing inconsistency
**Migration:** Use `ChatbotRetrievalService` instead

### Rule-Based Chatbot Table
**Location:** `chatbot` table, `Chatbot` model

**Status:** Legacy - not used in main retrieval pipeline
**Note:** Can be used for admin-configured responses but NOT for article retrieval

## Configuration

### Similarity Thresholds
```php
SIMILARITY_THRESHOLD = 0.05;        // Minimum for relevant results
HIGH_SIMILARITY_THRESHOLD = 0.15;   // High confidence threshold
```

### Field Weights (Title Boosting)
```php
WEIGHT_TITLE = 2.0;      // Title gets 2x weight
WEIGHT_EXCERPT = 1.5;    // Excerpt gets 1.5x weight
WEIGHT_KEYWORDS = 1.5;   // Keywords get 1.5x weight
WEIGHT_CONTENT = 1.0;    // Content gets normal weight
```

### Title Boost Bonus
- Additional 0-50% similarity bonus based on title match ratio
- If all query terms appear in title: +50% bonus
- If half query terms appear in title: +25% bonus

## Cache Management

### Cache Keys
- `chatbot:retrieval:vectors:normalized:{md5}` - TF-IDF vectors
- `chatbot:retrieval:idf` - IDF scores
- `chatbot:topics` - Dynamic topics

### Cache Invalidation
- Automatic on article create/update/delete via ArticleObserver
- Manual via `php artisan chatbot:reindex --force`
- Admin endpoints: `/admin/chatbot/rebuild-cache`, `/admin/chatbot/clear-cache`

## Debug Logging

Enabled in `ChatbotController`:
```php
Log::debug('Chatbot query', [
    'query' => $userMessage,
    'is_greeting' => $isGreeting,
]);

Log::debug('Chatbot retrieval result', [
    'query' => $userMessage,
    'found' => count($articles),
    'confidence' => $confidence,
]);
```

## Usage

### Reindex Articles
```bash
php artisan chatbot:reindex --force
```

### Clear Cache
```bash
php artisan cache:forget chatbot:retrieval:vectors:normalized
php artisan cache:forget chatbot:topics
```

### API Endpoints
- `POST /chatbot/get-response` - Main chat endpoint
- `GET /chatbot/search?q=keyword` - Search articles
- `POST /chatbot/topics` - Get topic suggestions
- `POST /chatbot/subtopics` - Get subtopic suggestions
- `POST /chatbot/article-suggestion` - Get article suggestion

## Academic Explainability

This implementation is fully explainable for thesis/research:

1. **Deterministic**: Same input always produces same output
2. **Transparent**: All algorithms are documented and visible
3. **Reproducible**: Can be replicated exactly
4. **Measurable**: Similarity scores are explicit and interpretable
5. **No Black Box**: No hidden layers or opaque transformations

### TF-IDF Formula
```
TF(t,d) = count(t in d) / total terms in d
IDF(t) = log(total documents / documents containing t)
TF-IDF(t,d) = TF(t,d) × IDF(t)
```

### Cosine Similarity
```
similarity(A,B) = (A · B) / (||A|| × ||B||)
```

## Files Modified

1. `app/Services/Chatbot/PreprocessingService.php` - Unified preprocessing
2. `app/Services/Chatbot/ChatbotRetrievalService.php` - Main retrieval engine
3. `app/Services/ArticleSearchService.php` - Deprecated
4. `app/Observers/ArticleObserver.php` - Updated to use ChatbotRetrievalService
5. `app/Console/Commands/ReindexChatbotArticles.php` - Simplified
6. `app/Providers/AppServiceProvider.php` - Updated service registration
7. `app/Http/Controllers/ChatbotController.php` - Thin controller
8. `resources/views/components/chatbot-widget.blade.php` - Enhanced UX

## Testing Recommendations

1. **Exact Title Match**: Search for exact article title - should return that article as #1
2. **Partial Match**: Search for partial terms - should return relevant articles
3. **No Match**: Search for irrelevant terms - should return fallback message
4. **Greeting**: Send "halo" - should return greeting response
5. **Cache Invalidation**: Update article - should reflect in next search
6. **Title Boost**: Search terms in title should rank higher than terms only in content

## Expected Behavior

### Before Unification
- Inconsistent results between different search methods
- Duplicate preprocessing logic
- Conflicting ranking systems
- Aggressive fallback to FULLTEXT/LIKE
- Unstable retrieval behavior

### After Unification
- Single consistent TF-IDF pipeline
- Unified preprocessing
- Stable, predictable ranking
- Proper confidence levels
- Reliable article retrieval
- Title matches rank higher
- Top 3 results with excerpts