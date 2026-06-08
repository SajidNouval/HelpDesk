# Simplified Custom Retrieval Heuristics

## Problem Statement

The previous implementation had too many manual ranking rules causing unstable results:

### Issues Identified

1. **Aggressive Fallback Logic**
   - Multiple fallback conditions in `applyTypesenseFallback()` (4+ conditions)
   - `detectTypoQuery()` had 6 aggressive checks
   - Complex fallback chains that override primary ranking

2. **Manual Score Overrides**
   - 20+ boost/penalty constants with extreme values:
     - `IMPORTANT_KEYWORD_EXACT_BOOST = 25.0`
     - `TECHNICAL_EXACT_TITLE_BOOST = 12.0`
     - `SECURITY_TOKEN_EXACT_BOOST = 10.0`
     - `DOMAIN_FIRST_BOOST = 6.0`
     - `CROSS_DOMAIN_PENALTY = -5.0`
   - These massive values caused ranking instability

3. **Excessive Domain Heuristics**
   - Hard domain rules with allowed/forbidden lists
   - Cross-domain penalties
   - Security domain filtering
   - Domain-first boost calculations

4. **TF-IDF Dominating Results**
   - 70% weight for TF-IDF on normal queries
   - Only 30% weight for Typesense
   - TF-IDF was overriding Typesense's fuzzy matching

## Solution: Trust Typesense Ranking

### New Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     SIMPLIFIED PIPELINE                      │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  1. Typesense (85% weight)                                   │
│     ├── Fuzzy matching with typo tolerance                   │
│     ├── Native ranking algorithm                             │
│     └── Primary signal for retrieval                         │
│                                                              │
│  2. TF-IDF (15% weight)                                      │
│     ├── Light reranking only                                 │
│     ├── Title match boost (0.5 max)                          │
│     ├── Exact phrase boost (0.3 max)                         │
│     └── Minor adjustments                                    │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Key Changes

#### 1. Weight Configuration
```php
// BEFORE: TF-IDF dominated
private const TYPESENSE_WEIGHT_FOR_NORMAL = 0.30; // 30%
private const TFIDF_WEIGHT_FOR_NORMAL = 0.70;      // 70%

// AFTER: Typesense is primary
private const TYPESENSE_WEIGHT = 0.85;  // 85% Typesense
private const TFIDF_WEIGHT = 0.15;       // 15% TF-IDF
```

#### 2. Boost Factors (Reduced)
```php
// BEFORE: Massive boosts
private const IMPORTANT_KEYWORD_EXACT_BOOST = 25.0;
private const TECHNICAL_EXACT_TITLE_BOOST = 12.0;
private const SECURITY_TOKEN_EXACT_BOOST = 10.0;
private const DOMAIN_FIRST_BOOST = 6.0;

// AFTER: Light boosts only
private const TITLE_MATCH_BOOST = 0.5;   // Light boost
private const EXACT_MATCH_BOOST = 0.3;   // Light boost
```

#### 3. Removed Components

The following were completely removed:

- `applyTypesenseFallback()` - No more aggressive fallback
- `detectTypoQuery()` - No more complex typo detection
- `filterArticlesByDomain()` - No hard domain filtering
- `applySecurityDomainFilter()` - No security domain exclusion
- `calculateCrossDomainPenalty()` - No cross-domain penalties
- `calculateDomainFirstBoost()` - No domain-first boosting
- `calculateSecurityTokenBoost()` - No security token boosting
- `calculateImportantKeywordBoost()` - No important keyword boosting
- `calculateGenericityPenalty()` - No genericity penalties
- `calculateTechnicalExactBoost()` - No technical token boosting
- `preventGenericFallback()` - No generic fallback prevention
- `applySecurityCategoryBoost()` - No security category boosting
- `$hardDomainRules` - Removed 70+ lines of domain rules

#### 4. Simplified Flow

```php
public function retrieve(string $query, int $limit = 5): array
{
    // 1. Typesense: Primary retrieval (85% weight)
    $typesenseResults = $this->typesenseService->search($query, 30);
    
    // 2. Get articles for light reranking
    $articles = $this->getArticlesForReranking($typesenseResults['results']);
    
    // 3. TF-IDF: Light reranking (15% weight)
    $tfidfSimilarities = $this->calculateTfidfSimilarities($queryVector, $vectors);
    $boostedSimilarities = $this->applyLightBoosting($tfidfSimilarities);
    
    // 4. Combine: 85% Typesense + 15% TF-IDF
    $combinedScores = $this->combineScores($typesenseCandidates, $boostedSimilarities);
    
    // 5. Build final results
    return $this->buildFinalResults($combinedScores, $articles, $limit);
}
```

## Code Reduction

| Metric | Before | After | Reduction |
|--------|--------|-------|-----------|
| Lines of Code | 3,033 | 545 | -82% |
| Constants | 25+ | 8 | -68% |
| Methods | 30+ | 18 | -40% |
| Boost/Penalty Values | 20+ | 2 | -90% |

## Benefits

1. **Stable Results**: No more wild ranking swings from massive boost values
2. **Predictable Behavior**: Typesense's proven algorithm handles most ranking
3. **Easier Maintenance**: Simple, understandable code
4. **Better Typo Handling**: Typesense's native typo tolerance works without complex fallbacks
5. **Consistent Performance**: No complex domain heuristics to maintain

## How Typesense Handles What We Removed

| Removed Feature | Typesense Native Handling |
|-----------------|---------------------------|
| Typo correction | Built-in fuzzy matching with `num_typos`, `infix` |
| Domain filtering | `query_by_weights` prioritizes title/category |
| Security boosting | `optional_filter_by` for category boosting |
| Exact match priority | `prioritize_exact_match: true` |
| Token matching | `token_separators`, `symbols_to_index` |

## Testing Recommendations

1. Test with typo queries: "viruss", "ransomwre", "lemot"
2. Test with domain-specific queries: "komputer lemot", "printer error"
3. Test with security queries: "virus", "malware", "ransomware"
4. Verify ranking stability across multiple runs

## Rollback Plan

The previous implementation is preserved in git history. If issues arise:
```bash
git revert <commit-hash>