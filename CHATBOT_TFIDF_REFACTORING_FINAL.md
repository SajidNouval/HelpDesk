# TF-IDF Chatbot Refactoring Summary

## Overview

This document summarizes the complete audit and refactoring of the TF-IDF chatbot retrieval pipeline. All changes maintain the academic explainability and retrieval-based nature of the chatbot while significantly improving retrieval accuracy, indexing consistency, and conversational behavior.

## Changes Made

### 1. PreprocessingService (`app/Services/Chatbot/PreprocessingService.php`)

**Improvements:**
- Expanded stopwords list from ~30 to 150+ Indonesian stopwords
- Added caching support with `preprocessWithCache()` method
- Improved stemming algorithm with more prefix/suffix patterns
- Added `isStopword()` utility method
- Added `normalizeForDisplay()` for display text normalization
- Consistent preprocessing for both query and documents

**Key Features:**
- Case folding → Cleaning → Tokenization → Stopword removal → Stemming
- Minimum token length: 2 characters (reduced from 3 for better recall)
- O(1) stopword lookup using array_flip()

### 2. ChatbotRetrievalService (`app/Services/Chatbot/ChatbotRetrievalService.php`)

**Major Improvements:**

#### Title Boosting
- Implemented field-specific weights:
  - Title: 2.0x weight (doubled in token frequency)
  - Excerpt: 1.5x weight
  - Keywords: 1.5x weight
  - Content: 1.0x weight
- Additional title boost: up to 50% similarity bonus if query terms match title

#### Improved Ranking
- Returns top 3 results (previously only 1)
- Confidence levels: high, medium, low based on similarity scores
- Similarity threshold: 0.05 (reasonable minimum)
- High confidence threshold: 0.15

#### Better Response Generation
- Time-aware greetings (pagi/siang/sore/malam)
- Confidence-based response templates
- More natural conversational responses
- Improved fallback messages

#### Cache Improvements
- Better cache key generation using MD5 of document IDs
- Cache validation based on document count
- Separate cache keys for vectors and IDF

### 3. ArticleObserver (`app/Observers/ArticleObserver.php`)

**Changes:**
- Now uses `ChatbotRetrievalService` instead of `ArticleSearchService`
- Checks both `is_published` AND `publish_status = 'approved'`
- Triggers cache rebuild on article create/update/delete
- Unified indexing logic

### 4. ReindexChatbotArticles Command (`app/Console/Commands/ReindexChatbotArticles.php`)

**Simplifications:**
- Now uses `ChatbotRetrievalService::rebuildCache()`
- Cleaner output with document and term counts
- Better error handling

### 5. AppServiceProvider (`app/Providers/AppServiceProvider.php`)

**Changes:**
- Registers all Chatbot services as singletons
- Ensures consistent dependency injection
- Proper service registration order

### 6. Chatbot Widget (`resources/views/components/chatbot-widget.blade.php`)

**UX Improvements:**
- Article cards now show:
  - Ranking number (#1, #2, #3)
  - Article title
  - Article excerpt (if available)
  - Similarity percentage
  - Confidence indicator (color-coded)
- Increased article section height for better display
- Better visual hierarchy

## Retrieval Pipeline (Academic Explanation)

```
User Query
    ↓
[Preprocessing] - Case folding, cleaning, tokenization, stopword removal, stemming
    ↓
[TF-IDF Vectorization] - Calculate term frequency, apply field weights
    ↓
[Cosine Similarity] - Compare query vector with document vectors
    ↓
[Title Boost] - Apply bonus if query terms match document title
    ↓
[Ranking] - Sort by boosted similarity, filter by threshold
    ↓
[Top 3 Results] - Return highest-ranked articles
    ↓
[Response Generation] - Generate natural language response with confidence
```

## Key Metrics

| Metric | Before | After |
|--------|--------|-------|
| Stopwords | ~30 | 150+ |
| Results returned | 1 | 3 |
| Similarity threshold | 0.02 | 0.05 |
| Title weight | 1.0 | 2.0 |
| Confidence levels | None | 3 levels |
| Cache validation | Document count only | MD5 hash + count |

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

## Academic Explainability

This chatbot implementation is fully explainable for thesis/research purposes:

1. **TF-IDF Calculation**: Standard TF-IDF formula with field weighting
2. **Cosine Similarity**: Standard vector cosine similarity
3. **Preprocessing**: Documented Indonesian language processing
4. **Ranking**: Transparent scoring with confidence levels
5. **No Black Box**: All algorithms are deterministic and explainable

## Testing Recommendations

1. Test with various Indonesian queries
2. Verify title boosting works correctly
3. Check that unpublished articles are not indexed
4. Verify cache invalidation on article updates
5. Test greeting responses at different times of day

## Files Modified

1. `app/Services/Chatbot/PreprocessingService.php`
2. `app/Services/Chatbot/ChatbotRetrievalService.php`
3. `app/Observers/ArticleObserver.php`
4. `app/Console/Commands/ReindexChatbotArticles.php`
5. `app/Providers/AppServiceProvider.php`
6. `resources/views/components/chatbot-widget.blade.php`

## Backward Compatibility

- All existing API endpoints remain unchanged
- Chatbot widget maintains same structure
- No database migrations required
- Existing articles will be automatically reindexed on next access