# IMPORTANT PHRASE BOOSTING IMPLEMENTATION

## Problem Statement

Short contextual queries were retrieving wrong articles due to token-based ranking:

- **Query**: "wifi tidak terhubung"
- **Wrong Result**: "Internet lambat" article
- **Expected Result**: "Wifi tidak terhubung" article

### Root Cause

Individual tokens (wifi, internet, lambat) dominated ranking while important phrases like "tidak terhubung" were not weighted strongly enough.

## Solution

Implemented phrase-level intent boosting through a new `ImportantPhraseService` that:

1. **Detects important phrases** in user queries
2. **Prioritizes phrase matches** over single token matches
3. **Supports n-gram matching** (bigram/trigram)
4. **Boosts title phrase matches** with highest priority
5. **Provides debug logging** for phrase detection and scoring

## Implementation Details

### New Service: `ImportantPhraseService`

Location: `app/Services/Chatbot/ImportantPhraseService.php`

#### Important Phrases Database

49 important phrases organized into 8 categories:

| Category | Phrases | Examples |
|----------|---------|----------|
| Connection Issues | 10 | tidak terhubung, putus nyambung, koneksi gagal |
| Detection Issues | 5 | tidak terbaca, tidak terdeteksi, tidak muncul |
| Login/Access Issues | 6 | gagal login, tidak bisa login, terkunci |
| Response Issues | 5 | tidak merespon, tidak respon, tidak responsif |
| Functionality Issues | 6 | tidak berfungsi, tidak bisa digunakan, tidak mau |
| Display Issues | 6 | tidak muncul, layar hitam, layar biru |
| Performance Issues | 6 | sangat lambat, lemot parah, hang, freeze |
| Error Issues | 5 | error terus, muncul error, pesan error |

#### Boosting Weights

```php
PHRASE_MATCH_BONUS = 0.4;        // Base bonus for phrase match in content
TITLE_PHRASE_BONUS = 0.6;        // Strong bonus for phrase match in title
EXACT_QUERY_PHRASE_BONUS = 0.8;  // Maximum bonus for exact query phrase in title
```

#### N-gram Scoring

- **Bigram match**: +0.15 per match (max 0.5)
- **Trigram match**: +0.25 per match (max 0.5)
- **Title bonus**: +0.1 for bigram, +0.15 for trigram

### Integration with `AdvancedRetrievalService`

The phrase boost is applied as a **direct additive bonus** (not weighted) to ensure phrase matches have strong influence on ranking:

```php
$finalScore = (
    ($cosineSimilarity * WEIGHT_COSINE) +
    ($titleOverlap * WEIGHT_TITLE_OVERLAP) +
    ($domainMatch * WEIGHT_DOMAIN_MATCH) +
    ($queryCoverage * WEIGHT_QUERY_COVERAGE) +
    ($exactPhraseBonus * WEIGHT_EXACT_PHRASE) +
    ($diversificationScore * WEIGHT_DIVERSIFICATION)
) + $domainPenalty + $securityBoost + $phraseBoost;
```

### Debug Logging

The service logs:
- Detected phrases in query
- Phrase matches per document
- Phrase boost score breakdown
- N-gram matches (bigram/trigram)
- Title phrase matches

## Test Results

### Test 1: Phrase Detection

```
Query: "wifi tidak terhubung" → Detects: "tidak terhubung" ✓
Query: "printer tidak terbaca" → Detects: "tidak terbaca" ✓
Query: "gagal login email" → Detects: "gagal login" ✓
Query: "internet putus nyambung" → Detects: "putus nyambung" ✓
Query: "internet lambat" → Detects: NONE (correct - not important phrase) ✓
```

### Test 2: Phrase Boost Scoring

For query "wifi tidak terhubung":

| Document | Phrase Boost | N-gram Boost | Total Boost |
|----------|-------------|--------------|-------------|
| Wifi Tidak Terhubung article | 1.0 | 0.9 | **1.0** (capped) |
| Internet Lambat article | 0.0 | 0.0 | **0.0** |
| Printer Tidak Terbaca article | 0.0 | 0.0 | **0.0** |
| Gagal Login article | 0.0 | 0.0 | **0.0** |

### Test 3: N-gram Matching

For query "wifi tidak terhubung":

| Document | Bigram Matches | Trigram Matches | Total N-gram Score |
|----------|---------------|-----------------|-------------------|
| "Tidak Terhubung ke Wifi" | tidak terhubung | - | 0.25 |
| "Wifi Tidak Terhubung" | wifi tidak, tidak terhubung | wifi tidak terhubung | **0.9** |
| "Internet Lambat" | - | - | 0.0 |

## Files Changed

1. **New File**: `app/Services/Chatbot/ImportantPhraseService.php`
   - Phrase detection and scoring service

2. **Modified**: `app/Services/Chatbot/AdvancedRetrievalService.php`
   - Added `ImportantPhraseService` dependency
   - Integrated phrase boosting into `hybridRanking()` method
   - Added debug logging for phrase detection

3. **New File**: `test_important_phrase_boosting.php`
   - Comprehensive test script

## Expected Behavior After Fix

| Query | Expected Result | Should NOT Return |
|-------|-----------------|-------------------|
| "wifi tidak terhubung" | Wifi tidak terhubung article | Internet lambat article |
| "printer tidak terbaca" | Printer tidak terbaca article | Generic troubleshooting |
| "gagal login" | Gagal login article | Generic login article |
| "internet putus nyambung" | Internet putus-putus article | Generic internet article |

## Verification

Run the test script:
```bash
php test_important_phrase_boosting.php
```

## Summary

This implementation solves the retrieval accuracy problem by:

1. **Detecting important phrases** that represent true user intent
2. **Applying strong boosting** when documents match these phrases
3. **Prioritizing title matches** with the highest bonus
4. **Supporting n-gram matching** for partial phrase matches
5. **Providing comprehensive debug logging** for troubleshooting

The phrase boost is applied as a direct additive bonus, ensuring that phrase matches have significantly higher influence than individual token matches.