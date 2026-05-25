# Hybrid Reranking Implementation Summary

## Problem Statement

Generic terms such as `lemot`, `error`, `cara`, `mengatasi` were overpowering exact-domain meaning in TF-IDF ranking. For example:
- Query: "komputer lemot" → generic "internet lemot" articles were outranking "komputer lemot" specific articles
- Query: "printer error" → articles from unrelated domains (BSOD, VPN, internet) were appearing in results

## Solution: Multi-Factor Hybrid Reranking

Implemented a comprehensive hybrid reranking system with 6 scoring components:

### 1. Title Overlap Scoring (Weight: 25%)
**File: `app/Services/Chatbot/AdvancedRetrievalService.php`**

- **Bigram matching**: Detects exact phrases like "komputer lemot" in titles
- **Low-priority term filtering**: Ignores generic terms (cara, mengatasi, solusi, etc.)
- **Domain-specific term boosting**: Gives 1.5x weight to domain terms (komputer, printer, wifi, etc.)

```php
// Example: "komputer lemot" bigram match in title "Mengatasi Komputer Lemot"
// → Bigram "komputer lemot" found → +0.3 bonus to title overlap score
```

### 2. Exact Phrase Bonus (Weight: 10%)
**File: `app/Services/Chatbot/AdvancedRetrievalService.php`**

- **Full query match in title**: 1.0 (maximum)
- **Important phrase match**: 0.9 (e.g., "komputer lemot" when query is "cara mengatasi komputer lemot")
- **All important words in title**: 0.75
- **Most words in title (75%+)**: 0.5
- **Excerpt match**: 0.4
- **Content match**: 0.3

### 3. Query Coverage Scoring (Weight: 15%)
**File: `app/Services/Chatbot/AdvancedRetrievalService.php`**

- Filters out low-priority and stopword terms
- **Full coverage bonus**: +0.25 when ALL important terms match
- **Partial coverage (75%+)**: +0.1
- **Domain-specific term weighting**: 1.5x for domain terms

### 4. Domain Match Boost (Weight: 15%)
**File: `app/Services/Chatbot/AdvancedRetrievalService.php`**

- Returns 1.0 if article category matches detected domain
- Returns 0.5 if no domain detected (neutral)
- Returns 0.0 if category doesn't match

### 5. Negative Domain Penalty
**File: `app/Services/Chatbot/AdvancedRetrievalService.php`**

- **Strong penalty (-0.8)**: For articles containing negative domain keywords in title/content
- **Standard penalty (-0.5)**: For articles in forbidden category

```php
// Example: printer query penalizes these domains:
'printer' => ['bsod', 'vpn', 'internet', 'wifi', 'security', 'email']
```

### 6. Low-Priority Term Weight Reduction
**File: `app/Services/Chatbot/TfidfService.php`**

- Applied at TF-IDF calculation level (both document and query vectors)
- **90% weight reduction** for generic terms:
  - `cara`, `mengatasi`, `solusi`, `tutorial`, `panduan`
  - `tips`, `langkah`, `metode`, `guide`, `help`, `bantuan`, `petunjuk`

```php
// Low-priority terms get 0.1x weight multiplier
if ($this->isLowPriorityTerm($term)) {
    $score *= self::LOW_PRIORITY_WEIGHT; // 0.1
}
```

## Weight Configuration

```php
WEIGHT_COSINE = 0.30;        // Base TF-IDF cosine similarity
WEIGHT_TITLE_OVERLAP = 0.25; // Title keyword overlap (increased)
WEIGHT_DOMAIN_MATCH = 0.15;  // Domain/category alignment
WEIGHT_QUERY_COVERAGE = 0.15; // Query term coverage (increased)
WEIGHT_EXACT_PHRASE = 0.10;  // Exact phrase match
WEIGHT_DIVERSIFICATION = 0.05; // Result diversity
```

## Expected Behavior After Implementation

| Query | Expected Top Result | Should NOT Contain |
|-------|---------------------|-------------------|
| `komputer lemot` | Komputer lemot article | internet lemot, wifi lemot |
| `printer error` | Printer troubleshooting | bsod, vpn, internet |
| `cara mengatasi komputer lemot` | Komputer lemot article | internet lemot, wifi lemot |
| `wifi tidak connect` | WiFi connection article | printer, email |
| `email tidak masuk` | Email troubleshooting | printer, hardware |

## Files Modified

1. **`app/Services/Chatbot/AdvancedRetrievalService.php`**
   - Added `$lowPriorityTerms` array
   - Added `$negativeDomainPenalties` mapping
   - Enhanced `calculateTitleOverlap()` with bigram matching
   - Enhanced `calculateExactPhraseBonus()` with phrase detection
   - Enhanced `calculateQueryCoverage()` with full coverage bonus
   - Enhanced `calculateDomainPenalty()` with negative domain checking
   - Added helper methods: `isLowPriorityTerm()`, `isDomainSpecificTerm()`, `calculateBigramOverlap()`

2. **`app/Services/Chatbot/TfidfService.php`**
   - Added `$lowPriorityTerms` array
   - Added `LOW_PRIORITY_WEIGHT` constant (0.1)
   - Modified `calculateTFIDF()` to reduce low-priority term weights
   - Modified `calculateQueryTFIDF()` to reduce low-priority term weights
   - Added `isLowPriorityTerm()` helper method

3. **`test_hybrid_reranking.php`** (new)
   - Comprehensive test cases for hybrid reranking verification

## Testing

Run the test file to verify the implementation:

```bash
php test_hybrid_reranking.php
```

## Key Improvements

1. **Exact Phrase Priority**: "komputer lemot" in title gets maximum score
2. **Query Coverage Boost**: Articles matching ALL important terms get +0.25 bonus
3. **Negative Domain Penalty**: Printer queries penalize BSOD/VPN/internet articles by -0.8
4. **Low Priority Filtering**: Generic terms like "cara", "mengatasi" have 90% reduced weight
5. **Domain-Specific Boosting**: Terms like "komputer", "printer", "wifi" get 1.5x weight in title overlap