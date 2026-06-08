# PREPROCESSING AND INDEXING STABILIZATION AUDIT REPORT

**Date:** 2026-05-24  
**System:** TF-IDF Chatbot - HelpDesk System  
**Auditor:** Claude Code Analysis  

---

## EXECUTIVE SUMMARY

This audit examined the preprocessing pipeline, indexing flow, database integrity, and retrieval stability of the TF-IDF chatbot system. The analysis covered:

- **PreprocessingService** - Tokenization, stemming, typo normalization, synonym handling
- **ChatbotRetrievalService** - Retrieval pipeline, cache management, vector building
- **TfidfService** - TF-IDF vectorization, IDF calculation
- **ArticleKeywordIndex** - Database schema, indexing model
- **ArticleObserver** - Auto-indexing triggers
- **ReindexChatbotArticles** - Manual reindex command

### OVERALL ASSESSMENT: ✅ STABLE WITH MINOR ISSUES

The preprocessing and indexing pipeline is **fundamentally sound** with good architecture. However, several issues were identified that need attention for full stability.

---

## 1. PREPROCESSING PIPELINE ANALYSIS

### ✅ STRENGTHS

1. **Consistent Pipeline Flow**
   - `preprocess()` method follows correct order: case folding → typo correction → cleaning → tokenization → stopword removal → stemming
   - Same pipeline used for both queries and documents
   - `preprocessDocument()` returns both tokens and frequency map

2. **Protected Technical Tokens**
   - 140+ protected technical tokens defined (ransomware, malware, virus, trojan, vpn, wifi, printer, bsod, gmail, outlook, etc.)
   - `isProtectedTechnicalToken()` check prevents stemming of critical terms
   - Protection applied BEFORE stemming in `stem()` method

3. **Typo Normalization**
   - Comprehensive typo dictionary with 100+ entries
   - Covers common typos: wfi→wifi, pritner→printer, emial→email, intenet→internet, kompter→komputer
   - `normalizeTypos()` method works correctly
   - `getTypoCorrections()` provides debug info

4. **Stopword Handling**
   - 200+ Indonesian stopwords defined
   - O(1) lookup using array_flip()
   - Proper filtering before stemming

### ⚠️ ISSUES IDENTIFIED

#### Issue 1.1: Typo Correction Timing Inconsistency
**Severity:** MEDIUM  
**Location:** `PreprocessingService.php:509-529`

**Problem:**
```php
public function preprocess(string $text, bool $applyTypoCorrection = false): array
{
    // ...
    if ($applyTypoCorrection) {
        $text = $this->normalizeTypos($text);
    }
    $text = $this->cleaning($text);
    // ...
}
```

Typo correction is applied BEFORE cleaning, which means:
- Typo dictionary contains entries like "wi-fi" → "wifi"
- But cleaning removes hyphens BEFORE tokenization
- This creates a mismatch where "wi-fi" becomes "wi fi" before typo correction can work

**Fix Required:**
Apply typo correction AFTER cleaning but BEFORE tokenization, OR ensure typo dictionary only contains clean tokens.

#### Issue 1.2: Stemming Protection Bypass
**Severity:** MEDIUM  
**Location:** `PreprocessingService.php:633-668`

**Problem:**
The stemming protection checks the word BEFORE any affix removal:
```php
private function stem(string $word): string
{
    if ($this->isProtectedTechnicalToken($word)) {
        return $word;
    }
    // ... stemming logic
}
```

But if the word has Indonesian affixes (e.g., "mengamankan" from "aman"), it won't match the protected token "aman" because the check is case-sensitive and exact-match only.

**Fix Required:**
Check protected tokens against the stemmed result, not just the original word.

#### Issue 1.3: Duplicate Entries in Typo Dictionary
**Severity:** LOW  
**Location:** `PreprocessingService.php:16-187`

**Problem:**
Multiple duplicate entries found:
- Line 18 & 23: 'wfi' → 'wifi' (duplicate)
- Line 42: 'jaringn' appears twice
- Line 80 & 82: 'lemot' appears twice
- Line 100 & 101: 'mau' appears twice
- Line 104 & 105: 'sudah' appears twice

This doesn't break functionality but wastes memory and causes confusion.

---

## 2. TYPO NORMALIZATION ANALYSIS

### ✅ STRENGTHS

1. **Comprehensive Dictionary**
   - 100+ typo corrections defined
   - Covers WiFi, Internet, Komputer, Printer, Email, Login, Password categories
   - Bidirectional corrections for common errors

2. **Debug Support**
   - `getTypoCorrections()` method tracks what was corrected
   - Useful for logging and debugging

### ⚠️ ISSUES IDENTIFIED

#### Issue 2.1: No Fuzzy Matching
**Severity:** MEDIUM

**Problem:**
Current implementation only does exact string matching:
```php
$corrected = $this->typoDictionary[$token] ?? $token;
```

If user types "wifii" (extra 'i'), it won't match "wiifi" or "wifii" in dictionary if not explicitly listed.

**Recommendation:**
Consider adding Levenshtein distance-based fuzzy matching for tokens not in dictionary (distance ≤ 2).

#### Issue 2.2: No Context-Aware Correction
**Severity:** LOW

**Problem:**
"wfi" could mean "wifi" or could be a typo for something else depending on context. Current implementation doesn't consider context.

---

## 3. STEMMING ANALYSIS

### ✅ STRENGTHS

1. **Protected Token List**
   - 140+ technical tokens protected from stemming
   - Includes security terms (ransomware, malware, virus, trojan)
   - Includes network terms (vpn, wifi, http, https, ftp, ssh)
   - Includes hardware terms (printer, router, switch, modem)
   - Includes software terms (windows, linux, gmail, outlook)

2. **Prefix/Suffix Removal**
   - Comprehensive Indonesian affix handling
   - Prefixes: meng, meny, mem, men, pen, peng, etc.
   - Suffixes: kan, an, i, nya, lah, etc.

### ⚠️ ISSUES IDENTIFIED

#### Issue 3.1: Protected Token Check is Case-Sensitive
**Severity:** MEDIUM  
**Location:** `PreprocessingService.php:676-679`

**Problem:**
```php
public function isProtectedTechnicalToken(string $token): bool
{
    return in_array(mb_strtolower($token), $this->protectedTechnicalTokens);
}
```

The check lowercases the input, but the `protectedTechnicalTokens` array contains lowercase entries. This is correct, BUT the `stem()` method calls `isProtectedTechnicalToken($word)` where `$word` might not be lowercased yet if it came from cleaning step.

**Verification:** The cleaning step uses `mb_strtolower()` in caseFolding, so this should be fine. But the order matters.

#### Issue 3.2: Stemming Order - Prefix Before Suffix
**Severity:** LOW

**Problem:**
Current implementation removes prefix first, then suffix. For Indonesian, sometimes suffix should be removed first (e.g., "berlarian" → "lari").

---

## 4. INDEXING FLOW ANALYSIS

### ✅ STRENGTHS

1. **Observer-Based Auto-Indexing**
   - `ArticleObserver` triggers on created, updated, deleted, restored, forceDeleted
   - Only indexes published AND approved articles
   - Automatic cache rebuild on changes

2. **Manual Reindex Command**
   - `php artisan chatbot:reindex --force` available
   - Provides feedback on documents and terms indexed

3. **Database Schema**
   - Proper ULID primary keys
   - Foreign key constraint to articles table
   - Unique constraint on (article_id, keyword) prevents duplicates
   - Index on (keyword, article_id) for fast lookups

### ⚠️ ISSUES IDENTIFIED

#### Issue 4.1: ArticleKeywordIndex Table NOT Used in Retrieval
**Severity:** HIGH  
**Location:** `ChatbotRetrievalService.php`

**Problem:**
The `article_keyword_index` table exists and has proper schema, but the retrieval pipeline does NOT use it. Instead, it:
1. Fetches articles from database
2. Preprocesses content in memory
3. Builds TF-IDF vectors in memory
4. Caches vectors in Redis/file cache

This means:
- The keyword index table is populated but never queried
- Indexing is redundant work
- No persistent term-based lookup

**Fix Required:**
Either:
A. Use the keyword index for retrieval (term → articles lookup)
B. Remove the keyword index table if not needed
C. Use keyword index as fallback when cache misses

#### Issue 4.2: Cache Invalidation Race Condition
**Severity:** MEDIUM  
**Location:** `ArticleObserver.php:32-40`

**Problem:**
```php
public function updated(Article $article): void
{
    if ($this->shouldIndex($article)) {
        $this->retrievalService->rebuildCache();
    } else {
        $this->retrievalService->rebuildCache();
    }
}
```

Both branches do the same thing! The logic should be:
- If shouldIndex: rebuild cache (article is now indexable)
- If NOT shouldIndex: rebuild cache (article was removed from index)

But the current code rebuilds cache regardless, which is inefficient.

**Fix Required:**
The logic is actually correct (always rebuild on update), but the if/else is redundant. Simplify to always rebuild.

#### Issue 4.3: No Error Handling in Observer
**Severity:** MEDIUM  
**Location:** `ArticleObserver.php`

**Problem:**
If `rebuildCache()` throws an exception, the article save operation could fail or the exception could be swallowed.

**Fix Required:**
Add try-catch with logging in observer methods.

---

## 5. DATABASE/INDEX INTEGRITY ANALYSIS

### ✅ STRENGTHS

1. **Proper Schema Design**
   - ULID primary keys (globally unique, sortable)
   - Foreign key with cascade delete
   - Unique constraint prevents duplicate keyword entries per article
   - Composite index for fast keyword lookups

2. **Type Safety**
   - `tf` column is float type
   - `field_boosts` is JSON type with array cast

### ⚠️ ISSUES IDENTIFIED

#### Issue 5.1: No Timestamps on Index Table
**Severity:** LOW  
**Location:** `database/migrations/2026_04_21_100000_create_article_keyword_index_table.php`

**Problem:**
```php
public $timestamps = false;
```

No created_at/updated_at means we can't track when index entries were created or last updated. This makes debugging stale indexes difficult.

**Recommendation:**
Add timestamps for audit trail.

#### Issue 5.2: No Index Validation Command
**Severity:** MEDIUM

**Problem:**
There's no command to validate that:
- All published articles have index entries
- No orphaned index entries exist
- Index entries match current article content

**Fix Required:**
Add `php artisan chatbot:validate-index` command.

---

## 6. CACHE CONSISTENCY ANALYSIS

### ✅ STRENGTHS

1. **Multiple Cache Layers**
   - Vector cache (TF-IDF vectors)
   - IDF cache (inverse document frequency)
   - Topic cache (dynamic topics)

2. **Cache Invalidation**
   - `clearCache()` method clears all caches
   - `rebuildCache()` clears and rebuilds

3. **TTL Configuration**
   - Vector cache: 24 hours
   - Topic cache: 1 hour

### ⚠️ ISSUES IDENTIFIED

#### Issue 6.1: Cache Key Generation Vulnerability
**Severity:** MEDIUM  
**Location:** `ChatbotRetrievalService.php:762-764`

**Problem:**
```php
$docIds = implode(',', array_keys($documents));
$cacheKey = self::VECTOR_CACHE_KEY . ':' . md5($docIds);
```

Cache key is based on document IDs. If articles are added/removed, the cache key changes, which is good. But:
- MD5 collision risk (low but non-zero)
- No version number in cache key
- Cache doesn't invalidate when preprocessing rules change

**Fix Required:**
Add a version/revision number to cache keys that increments when preprocessing rules change.

#### Issue 6.2: No Cache Warming Strategy
**Severity:** LOW

**Problem:**
After cache clear, the first query triggers full vector rebuild. For large article sets, this could be slow.

**Recommendation:**
Add cache warming on application startup or via scheduled job.

---

## 7. RETRIEVAL PIPELINE STABILITY

### ✅ STRENGTHS

1. **Consistent Preprocessing**
   - Query preprocessing uses same pipeline as document preprocessing
   - Typo correction applied to queries via `applyTypoCorrection = true`

2. **Multiple Ranking Factors**
   - Base TF-IDF similarity
   - Domain token boost
   - Query coverage boost
   - Exact phrase boost
   - Title match boost
   - Cross-domain penalty
   - Security token boost

3. **Debug Mode**
   - `$debugMode` flag enables detailed logging
   - Tracks all scoring components

### ⚠️ ISSUES IDENTIFIED

#### Issue 7.1: Query Vector Calculation Uses Typo Correction
**Severity:** LOW  
**Location:** `TfidfService.php:117-137`

**Problem:**
```php
public function calculateQueryTFIDF(string $query, array $idf): array
{
    $tokens = $this->preprocessor->preprocess($query, true); // true = typo correction
    // ...
}
```

This is CORRECT - typo correction IS applied to queries. But the `ChatbotRetrievalService` also calls `normalizeQuery()` separately:

```php
private function normalizeQuery(string $query): string
{
    $correctedQuery = $this->preprocessor->normalizeTypos($query);
    // ...
}
```

This means typo correction is applied TWICE:
1. In `normalizeQuery()` 
2. In `calculateQueryTFIDF()` via preprocess($query, true)

**Fix Required:**
Remove duplicate typo correction. Either:
A. Apply in normalizeQuery() and use preprocess($normalizedQuery, false)
B. Don't apply in normalizeQuery() and use preprocess($query, true)

#### Issue 7.2: No Query Validation Before Retrieval
**Severity:** LOW

**Problem:**
If query results in empty tokens after preprocessing, the system returns empty results. But there's no logging of WHY it was empty (all stopwords? all filtered?).

**Fix Required:**
Add debug logging for empty query token cases.

---

## 8. LOGGING ANALYSIS

### ✅ STRENGTHS

1. **Debug Mode Available**
   - `$debugMode` flag in ChatbotRetrievalService
   - Logs detailed scoring breakdown when enabled

2. **Basic Query Logging**
   - ChatbotController logs queries and results

### ⚠️ ISSUES IDENTIFIED

#### Issue 8.1: Insufficient Preprocessing Logs
**Severity:** MEDIUM

**Problem:**
No logging of:
- Original query vs normalized query
- Typo corrections applied
- Protected technical tokens detected
- Stemming results
- Tokens removed as stopwords

**Fix Required:**
Add comprehensive preprocessing debug logging.

#### Issue 8.2: No Indexing Failure Logs
**Severity:** MEDIUM

**Problem:**
If indexing fails (e.g., database error), there's no logging of which article failed or why.

**Fix Required:**
Add error logging in ArticleObserver and ReindexChatbotArticles command.

---

## RECOMMENDED FIXES (PRIORITY ORDER)

### HIGH PRIORITY

1. **Fix Issue 4.1: ArticleKeywordIndex Not Used**
   - Decision: Either use the index table or remove it
   - If keeping: Modify retrieval to use term-based lookup
   - If removing: Delete table, model, and migration

2. **Fix Issue 7.1: Duplicate Typo Correction**
   - Remove duplicate typo correction in normalizeQuery() or calculateQueryTFIDF()
   - Ensure consistent single application

3. **Fix Issue 4.3: Add Error Handling in Observer**
   - Wrap rebuildCache() calls in try-catch
   - Log errors without failing article save

### MEDIUM PRIORITY

4. **Fix Issue 1.1: Typo Correction Timing**
   - Apply typo correction after cleaning
   - Or ensure dictionary only has clean tokens

5. **Add Index Validation Command**
   - Create `php artisan chatbot:validate-index`
   - Check for orphaned entries, missing indexes

6. **Add Comprehensive Debug Logging**
   - Log preprocessing steps
   - Log typo corrections
   - Log protected token detection
   - Log indexing failures

### LOW PRIORITY

7. **Clean Up Duplicate Typo Dictionary Entries**
   - Remove duplicates from $typoDictionary

8. **Add Timestamps to Index Table**
   - Add created_at/updated_at for audit trail

9. **Add Cache Version Number**
   - Include version in cache keys
   - Invalidate on preprocessing rule changes

---

## VERIFICATION CHECKLIST

After implementing fixes, verify:

- [ ] Query "wfi" returns wifi-related articles
- [ ] Query "pritner" returns printer-related articles  
- [ ] Query "ransomware" returns security articles (not generic articles)
- [ ] Query "malware" returns security articles
- [ ] Technical terms (vpn, wifi, printer, bsod) are NOT stemmed
- [ ] Article updates trigger cache rebuild
- [ ] Article deletion triggers cache rebuild
- [ ] Reindex command works correctly
- [ ] Cache clear/rebuild cycle works
- [ ] No duplicate index entries exist
- [ ] Preprocessing logs show full pipeline

---

## CONCLUSION

The TF-IDF chatbot preprocessing and indexing system is **well-architected** with proper separation of concerns. The main issues are:

1. **Redundant keyword index table** that's not used in retrieval
2. **Duplicate typo correction** applied twice
3. **Missing error handling** in observer
4. **Insufficient debug logging** for troubleshooting

Implementing the recommended fixes will make the system **stable, deterministic, and fully consistent**.