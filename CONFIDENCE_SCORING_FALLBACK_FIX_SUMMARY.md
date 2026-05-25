# Confidence Scoring & Fallback Safety Fix

## Problem Statement

The chatbot's fallback mechanism was too aggressive, returning unrelated IT articles for weak/unclear queries. Low-confidence retrieval sometimes returned random articles that had no real relevance to the user's query.

## Root Causes

1. **Thresholds too low**: `SIMILARITY_THRESHOLD` was 0.05 and `HIGH_SIMILARITY_THRESHOLD` was 0.15 - far too permissive
2. **No safe fallback**: When all retrieval signals were weak, the system still returned articles instead of admitting uncertainty
3. **Single-factor decision**: Confidence was based primarily on final score without considering multiple signals

## Solution Implemented

### 1. Increased Confidence Thresholds

```php
// Before
SIMILARITY_THRESHOLD = 0.05
HIGH_SIMILARITY_THRESHOLD = 0.15

// After
SIMILARITY_THRESHOLD = 0.12        // Minimum score to include a result
HIGH_SIMILARITY_THRESHOLD = 0.35   // Score for high confidence
VERY_HIGH_SIMILARITY_THRESHOLD = 0.55 // Score for very high confidence
SAFE_FALLBACK_THRESHOLD = 0.18     // Below this, use safe fallback
```

### 2. Multi-Signal Fallback Detection

Added `shouldUseSafeFallback()` method that checks multiple signals:

- **Top score below SAFE_FALLBACK_THRESHOLD** (0.18)
- **No strong title overlap** (exact phrase match)
- **Low query coverage**
- **No exact phrase match**

Only if ALL signals are weak does the system use the safe fallback message.

### 3. Safe Fallback Response

When all retrieval signals are weak, instead of returning unrelated articles:

```php
// Returns a helpful message like:
"Maaf, saya kurang yakin dengan jawaban yang tepat untuk pertanyaan ini 🤔

Bisa coba jelaskan lebih spesifik? Misalnya:
• Sebutkan perangkat yang bermasalah (wifi, printer, komputer, dll)
• Jelaskan gejala atau error yang muncul
• Sertakan pesan error jika ada"
```

The response includes:
- `confidence: 'very_low'`
- `is_safe_fallback: true`
- `suggestions`: Category chips for guided navigation
- `show_contact_button: true`

### 4. Enhanced Confidence Levels

Added `very_low` confidence level for borderline cases:
- `high`: score >= 0.35
- `medium`: score >= 0.12
- `low`: score >= 0.12 but < 0.18 (with strong signals)
- `very_low`: score < 0.144 (1.2x threshold) - shows contact button

## Files Modified

- `app/Services/Chatbot/AdvancedRetrievalService.php`

## Key Changes

1. **Line 26-33**: Updated threshold constants
2. **Line 1660-1708**: Added `shouldUseSafeFallback()` method
3. **Line 1710-1740**: Added `getSafeFallbackResponse()` method
4. **Line 1742-1790**: Updated `formatResponse()` to use safe fallback

## Expected Behavior

### Before Fix
- Query: "lemot" (too generic)
- Result: Returns random IT articles about printers, BSOD, etc.

### After Fix
- Query: "lemot" (too generic)
- Result: Safe fallback message asking for clarification + category suggestions

### Before Fix
- Query: "wifi error" (weak match)
- Result: Returns articles with score 0.06 that have no wifi content

### After Fix
- Query: "wifi error" (weak match)
- Result: If all signals weak → safe fallback; if any strong signal → shows results with appropriate confidence

## Testing Recommendations

1. Test with very generic queries: "lemot", "error", "tidak bisa"
2. Test with weak matches: queries that partially match unrelated articles
3. Test with strong matches: ensure good queries still return results
4. Verify the safe fallback messages are helpful and guide users

## Impact

- **Reduced false positives**: Weak queries no longer return random articles
- **Better user experience**: Users get honest feedback when the bot is uncertain
- **Guided navigation**: Safe fallback includes category suggestions to help users refine
- **Maintained coverage**: Strong queries still return relevant results