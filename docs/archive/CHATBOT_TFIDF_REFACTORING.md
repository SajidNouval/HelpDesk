# TF-IDF Chatbot Refactoring Documentation

## Overview

This document describes the refactoring of the hybrid rule-based chatbot into a cleaner full retrieval-based TF-IDF chatbot with interactive features. The new implementation uses TF-IDF (Term Frequency-Inverse Document Frequency) vectorization and cosine similarity for document retrieval, combined with dynamic topic suggestions for a more engaging user experience.

## Architecture

### Service-Based Structure

```
app/Services/Chatbot/
├── PreprocessingService.php      # Text preprocessing (tokenization, stopwords, stemming)
├── TfidfService.php              # TF-IDF vectorization
├── CosineSimilarityService.php   # Cosine similarity calculation
└── ChatbotRetrievalService.php   # Main retrieval orchestrator + interactive features
```

### Data Flow

```
User Query / Topic Selection
    ↓
Preprocessing (case folding, tokenization, stopword removal, stemming)
    ↓
TF-IDF Query Vector (using pre-computed IDF from documents)
    ↓
Cosine Similarity (compare query vector with all document vectors)
    ↓
Ranking (sort by similarity score)
    ↓
Best Matching Response (with threshold check)
    ↓
Article Suggestion Card + Related Articles
```

### Interactive Flow

```
Widget Open
    ↓
Load Dynamic Topics (from categories/articles)
    ↓
User Clicks Topic
    ↓
Load Subtopics (related articles)
    ↓
User Clicks Subtopic
    ↓
Show Article Suggestion Card
    ↓
User Opens Article
```

## Services

### 1. PreprocessingService

Handles text normalization with the following steps:

1. **Case Folding**: Convert to lowercase
2. **Cleaning**: Remove special characters
3. **Tokenization**: Split into words
4. **Stopword Removal**: Remove common Indonesian words
5. **Stemming**: Reduce words to root form

```php
$preprocessor = app(PreprocessingService::class);
$tokens = $preprocessor->preprocess("Bagaimana cara reset password?");
// Result: ['cara', 'reset', 'password']
```

### 2. TfidfService

Calculates TF-IDF scores:

- **TF (Term Frequency)**: `count(term) / total_terms_in_document`
- **IDF (Inverse Document Frequency)**: `log(total_documents / documents_containing_term)`
- **TF-IDF**: `TF * IDF`

```php
$tfidfService = app(TfidfService::class);
$vectors = $tfidfService->buildTfidfVectors($documents);
// Returns: ['vectors' => [...], 'idf' => [...], 'docCount' => N]
```

### 3. CosineSimilarityService

Calculates cosine similarity between vectors:

```
cosine_similarity(A, B) = (A · B) / (||A|| * ||B||)
```

```php
$similarityService = app(CosineSimilarityService::class);
$score = $similarityService->calculate($vectorA, $vectorB);
// Returns: float between 0 and 1
```

### 4. ChatbotRetrievalService

Main orchestrator that:

1. Fetches published & approved articles
2. Builds/retrieves cached TF-IDF vectors
3. Calculates query vector
4. Computes similarity scores
5. Ranks and filters results
6. Formats response

```php
$retrievalService = app(ChatbotRetrievalService::class);
$result = $retrievalService->retrieve("cara reset password", 5);
// Returns: ['results' => [...], 'query' => '...', 'total' => N, 'threshold_met' => bool]
```

## Key Features

### 1. Caching

TF-IDF vectors are cached for 24 hours to improve performance:

```php
Cache::put('chatbot:retrieval:vectors', $tfidfData, 86400);
```

### 2. Similarity Threshold

Minimum similarity threshold (0.05) filters irrelevant results:

```php
private const SIMILARITY_THRESHOLD = 0.05;
```

### 3. Greeting Detection

Handles greetings separately for better UX:

```php
if ($retrievalService->isGreeting($query)) {
    return $retrievalService->getGreetingResponse();
}
```

### 4. Natural Response Generation

Uses templates for conversational responses:

```php
"Berdasarkan artikel yang saya temukan, **{$title}** mungkin dapat membantu Anda."
```

## Controller Changes

The `ChatbotController` is now simplified:

```php
public function getResponse(Request $request): JsonResponse
{
    // 1. Validate input
    // 2. Check for greeting
    // 3. Retrieve using TF-IDF
    // 4. Format and return response
}
```

## Routes

### Public Routes
- `POST /chatbot/get-response` - Main chatbot endpoint
- `POST /chatbot/search` - Article search
- `POST /chatbot/create-ticket` - Create support ticket

### Admin Routes
- `POST /admin/chatbot/rebuild-cache` - Rebuild TF-IDF cache
- `POST /admin/chatbot/clear-cache` - Clear TF-IDF cache

## Testing

Run the test script:

```bash
php test_tfidf_chatbot.php
```

Expected output shows:
- Preprocessing results
- TF-IDF calculations
- Cosine similarity scores
- Retrieval results

## Academic Explanation

### TF-IDF Formula

For term `t` in document `d`:

```
TF-IDF(t, d) = TF(t, d) × IDF(t)
```

Where:
- `TF(t, d) = count(t in d) / total_terms_in_d`
- `IDF(t) = log(total_documents / documents_containing_t)`

### Cosine Similarity

For query vector `Q` and document vector `D`:

```
similarity(Q, D) = (Q · D) / (||Q|| × ||D||)
```

This measures the cosine of the angle between vectors, ranging from 0 (orthogonal/unrelated) to 1 (identical direction/related).

## Performance Considerations

1. **Caching**: TF-IDF vectors cached for 24 hours
2. **Lazy Loading**: Vectors built on first request
3. **Sparse Vectors**: Only non-zero terms stored
4. **Database Optimization**: Only published/approved articles queried

## Future Improvements

1. **Sastrawi Integration**: Replace simple stemmer with Sastrawi for better Indonesian stemming
2. **Bigram/Trigram**: Support multi-word terms
3. **Field Boosting**: Weight title/excerpt higher than content
4. **Query Expansion**: Synonym handling for better matching