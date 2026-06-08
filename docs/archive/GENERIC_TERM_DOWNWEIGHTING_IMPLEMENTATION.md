# Generic Technical Term Downweighting Implementation

## Problem Statement

Generic technical words were dominating the TF-IDF scoring and retrieval ranking, causing irrelevant results:

- `pc`
- `laptop`
- `komputer`
- `aplikasi`
- `error`

These words appear in almost every helpdesk article, so they have high document frequency and contribute significantly to cosine similarity scores, but they don't help distinguish between relevant and irrelevant articles.

## Solution

Applied a **0.1x weight multiplier** (90% reduction) to generic technical terms during:

1. **TF-IDF Score Calculation** (`TfidfService`)
2. **Title Overlap Scoring** (`AdvancedRetrievalService`)
3. **Query Coverage Scoring** (`AdvancedRetrievalService`)
4. **Exact Phrase Matching** (`AdvancedRetrievalService`)

## Files Modified

### 1. `app/Services/Chatbot/TfidfService.php`

Added generic technical terms to the `$lowPriorityTerms` array:

```php
private array $lowPriorityTerms = [
    // Generic instructional words (existing)
    'cara', 'mengatasi', 'solusi', 'tutorial', 'panduan', 'tips',
    'langkah', 'metode', 'guide', 'help', 'bantuan', 'petunjuk',
    
    // Generic technical/device words (NEW)
    'pc', 'laptop', 'komputer', 'aplikasi', 'error', 'masalah',
    'sistem', 'program', 'software', 'hardware', 'teknologi',
    'digital', 'online', 'internet', 'jaringan', 'data', 'file', 'dokumen',
];
```

The `calculateTFIDF()` and `calculateQueryTFIDF()` methods apply the 0.1x multiplier:

```php
if ($this->isLowPriorityTerm($term)) {
    $score *= self::LOW_PRIORITY_WEIGHT; // 0.1
}
```

### 2. `app/Services/Chatbot/AdvancedRetrievalService.php`

1. **Added generic technical terms to `$lowPriorityTerms`** - Same list as TfidfService

2. **Removed generic terms from `isDomainSpecificTerm()`** - These terms were previously being boosted, which conflicted with the downweighting goal:

```php
// BEFORE (conflicting - boosting generic terms)
$domainTerms = [
    'wifi', 'internet', 'jaringan', 'printer', 'komputer', 'laptop', 'pc',
    'email', 'website', 'aplikasi', 'akun', 'login', 'password',
    'lemot', 'error', 'bsod', 'hang', 'crash', 'virus', 'malware',
    // ...
];

// AFTER (only specific terms are boosted)
$domainTerms = [
    'wifi', 'internet', 'jaringan', 'printer', 
    'email', 'website', 'akun', 'login', 'password',
    'lemot', 'bsod', 'hang', 'crash', 'virus', 'malware',
    'router', 'switch', 'hub', 'modem', 'driver', 'browser',
];
```

3. **Title overlap and query coverage** now filter out low-priority terms when calculating scores, ensuring generic terms don't dominate these ranking factors.

## Test Results

```
Query: 'laptop lemot'
  Token weights after downweighting:
    lemot          : 1.126381        (specific term - HIGH weight)
    laptop         : 0.099041 [DOWNWEIGHTED]  (generic term - LOW weight)

Query: 'komputer lemot error'
  Generic term 'komputer': weight = 0.066
  Generic term 'error': weight = 0.075
  Specific term 'lemot': weight = 0.751  (10x higher than generic terms!)
```

## Expected Impact

- **Before**: Queries like "komputer error" would match any article containing "komputer" or "error" (which is almost every article)
- **After**: Queries like "komputer error" will prioritize articles matching the specific issue (e.g., "lemot", "hang", "crash") over articles that just mention "komputer" generically

## Weight Configuration

The downweighting factor can be adjusted by modifying the constant:

```php
private const LOW_PRIORITY_WEIGHT = 0.1; // 0.1 = 90% reduction
```

- `0.1` = 90% reduction (current - recommended)
- `0.2` = 80% reduction
- `0.05` = 95% reduction (more aggressive)

## Generic Terms List

The following terms are considered "generic" and are downweighted:

### Generic Instructional Words
- cara, mengatasi, solusi, tutorial, panduan, tips
- langkah, metode, guide, help, bantuan, petunjuk

### Generic Technical/Device Words
- pc, laptop, komputer, aplikasi, error, masalah
- sistem, program, software, hardware, teknologi
- digital, online, internet, jaringan, data, file, dokumen

Note: "internet" and "jaringan" are in the stopwords list (completely filtered) in `AdvancedRetrievalService`, but they are NOT in the low-priority list in `TfidfService` because they are used for domain detection.