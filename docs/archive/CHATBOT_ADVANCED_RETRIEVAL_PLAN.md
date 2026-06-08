# 🔥 CHATBOT ADVANCED RETRIEVAL ENGINEERING PLAN

**Goal:** Transform the chatbot into a highly accurate, context-aware, guided retrieval helpdesk assistant using advanced TF-IDF retrieval engineering techniques.

**Constraints:**
- Do NOT replace TF-IDF
- Do NOT migrate to external search engines
- Do NOT use embeddings or AI/LLM
- System must remain deterministic, explainable, academically valid

---

## 📊 CURRENT STATUS (95.9% Pass Rate)

### Already Implemented ✅
1. **Domain-first candidate filtering** - Category-based filtering before TF-IDF
2. **Synonym normalization** - lambat→lemot, pelan→lemot, etc.
3. **Typo correction** - wfi→wifi, intenet→internet, etc.
4. **Title boosting** - Articles with matching titles get higher scores
5. **Context boosting** - Domain-specific tokens get additional weight
6. **Diversification** - Penalty for repeated categories/titles
7. **Out-of-domain rejection** - Non-IT queries are rejected
8. **Curated category system** - Clean chips without "jamal" pollution
9. **Multi-intent support** - Queries with "dan" are handled
10. **Escalation flow** - Contact button for low confidence

### Remaining Enhancements 🚧
1. **Query expansion** - Expand queries with semantic synonyms before TF-IDF
2. **Hybrid multi-stage ranking** - Title overlap + phrase match + query coverage
3. **Hybrid final score** - Weighted combination of multiple signals
4. **Query coverage boost** - Documents matching ALL query terms rank higher
5. **Negative domain penalty** - Penalize unrelated domains
6. **Exact phrase boost** - Strong boost for exact phrase matches in title
7. **IT-specific stopword system** - Lower impact for IT filler words
8. **Query intent classification** - Detect troubleshooting vs tutorial intent
9. **Result confidence grouping** - Categorize by relevance level
10. **Failure memory** - Track repeated failures for escalation

---

## 🏗️ ARCHITECTURE OVERVIEW

```
Query Input
    ↓
[1] Typo Correction & Synonym Normalization
    ↓
[2] Domain Detection (hard filtering)
    ↓
[3] Query Expansion (add semantic synonyms)
    ↓
[4] Multi-Intent Splitting (if "dan" detected)
    ↓
[5] Category-Filtered Article Retrieval
    ↓
[6] TF-IDF Vectorization (with expanded query)
    ↓
[7] Cosine Similarity Calculation
    ↓
[8] Hybrid Re-ranking:
    - Title overlap score (0.20)
    - Category match score (0.15)
    - Query coverage score (0.10)
    - Exact phrase bonus (0.10)
    - Diversification bonus (0.05)
    - Cosine similarity (0.40)
    ↓
[9] Negative Domain Penalty Application
    ↓
[10] Result Deduplication & Limiting
    ↓
[11] Confidence Grouping
    ↓
Response Output
```

---

## 📝 IMPLEMENTATION DETAILS

### FEATURE 1: Query Expansion (Before TF-IDF)

**Location:** `PreprocessingService.php`

```php
// Query expansion dictionary
private array $queryExpansionMap = [
    'wifi' => ['jaringan', 'wireless', 'hotspot', 'router', 'koneksi'],
    'internet' => ['koneksi', 'jaringan', 'bandwidth', 'online'],
    'printer' => ['cetak', 'printing', 'mencetak', 'epson', 'canon'],
    'komputer' => ['pc', 'laptop', 'desktop', 'notebook'],
    'email' => ['surel', 'mail', 'gmail', 'outlook', 'pesan'],
    'lemot' => ['lambat', 'pelan', 'slow'],
    'error' => ['masalah', 'gagal', 'rusak', 'troubleshoot'],
];

public function expandQuery(string $query): string {
    $tokens = explode(' ', $query);
    $expanded = [];
    foreach ($tokens as $token) {
        $expanded[] = $token;
        if (isset($this->queryExpansionMap[$token])) {
            $expanded = array_merge($expanded, $this->queryExpansionMap[$token]);
        }
    }
    return implode(' ', array_unique($expanded));
}
```

### FEATURE 2: Hybrid Final Score

**Location:** `ChatbotRetrievalService.php`

```php
// Weight configuration
private const WEIGHT_COSINE = 0.40;
private const WEIGHT_TITLE = 0.20;
private const WEIGHT_CATEGORY = 0.15;
private const WEIGHT_COVERAGE = 0.10;
private const WEIGHT_PHRASE = 0.10;
private const WEIGHT_DIVERSITY = 0.05;

private function calculateHybridScore(
    float $cosineSimilarity,
    float $titleOverlap,
    float $categoryMatch,
    float $queryCoverage,
    float $exactPhraseBonus,
    float $diversificationBonus
): float {
    return ($cosineSimilarity * self::WEIGHT_COSINE)
         + ($titleOverlap * self::WEIGHT_TITLE)
         + ($categoryMatch * self::WEIGHT_CATEGORY)
         + ($queryCoverage * self::WEIGHT_COVERAGE)
         + ($exactPhraseBonus * self::WEIGHT_PHRASE)
         + ($diversificationBonus * self::WEIGHT_DIVERSITY);
}
```

### FEATURE 3: Query Coverage Boost

```php
private function calculateQueryCoverage(array $queryTokens, array $docTokens): float {
    if (empty($queryTokens)) return 0.0;
    
    $matchedTerms = 0;
    foreach ($queryTokens as $token) {
        if (in_array($token, $docTokens)) {
            $matchedTerms++;
        }
    }
    
    return $matchedTerms / count($queryTokens);
}
```

### FEATURE 4: Negative Domain Penalty

```php
private array $domainConflicts = [
    'wifi' => ['printer', 'email', 'komputer'],
    'printer' => ['wifi', 'email', 'internet'],
    'email' => ['wifi', 'printer', 'komputer'],
    'komputer' => ['wifi', 'printer', 'internet'],
];

private function applyNegativeDomainPenalty(
    float $score, 
    string $detectedDomain, 
    string $articleCategory
): float {
    $conflicts = $this->domainConflicts[$detectedDomain] ?? [];
    if (in_array($articleCategory, $conflicts)) {
        return $score * 0.3; // 70% penalty for cross-domain articles
    }
    return $score;
}
```

### FEATURE 5: Exact Phrase Boost

```php
private function calculateExactPhraseBonus(string $query, string $title): float {
    $lowerQuery = mb_strtolower(trim($query));
    $lowerTitle = mb_strtolower(trim($title));
    
    // Check if exact query phrase appears in title
    if (str_contains($lowerTitle, $lowerQuery)) {
        return 1.0; // Maximum bonus
    }
    
    // Check for partial phrase match
    $queryWords = explode(' ', $lowerQuery);
    $consecutiveMatches = 0;
    foreach ($queryWords as $word) {
        if (str_contains($lowerTitle, $word)) {
            $consecutiveMatches++;
        }
    }
    
    return ($consecutiveMatches / count($queryWords)) * 0.5;
}
```

### FEATURE 6: IT-Specific Stopword System

```php
private array $itStopwords = [
    'cara', 'mengatasi', 'sistem', 'aplikasi', 'masalah',
    'tutorial', 'panduan', 'cara', 'langkah', 'berikut',
    'ini', 'itu', 'yang', 'untuk', 'dengan', 'pada',
];

private function filterITStopwords(array $tokens): array {
    return array_filter($tokens, fn($t) => !in_array($t, $this->itStopwords));
}
```

### FEATURE 7: Query Intent Classification

```php
private array $intentPatterns = [
    'troubleshooting' => ['error', 'lemot', 'tidak', 'gagal', 'masalah', 'rusak'],
    'tutorial' => ['cara', 'bagaimana', 'langkah', 'panduan', 'tutorial'],
    'configuration' => ['setting', 'konfigurasi', 'atur', 'setup'],
    'installation' => ['instal', 'pasang', 'download', 'unduh'],
];

private function detectIntent(string $query): string {
    $lowerQuery = mb_strtolower($query);
    
    foreach ($this->intentPatterns as $intent => $patterns) {
        foreach ($patterns as $pattern) {
            if (str_contains($lowerQuery, $pattern)) {
                return $intent;
            }
        }
    }
    
    return 'troubleshooting'; // Default intent
}
```

### FEATURE 8: Result Confidence Grouping

```php
private function getConfidenceGroup(float $score): string {
    if ($score >= 0.80) return 'sangat_relevan';
    if ($score >= 0.60) return 'relevan';
    if ($score >= 0.40) return 'mungkin_membantu';
    return 'rendah';
}
```

---

## 🧪 VALIDATION IMPROVEMENTS

### Current Test Issues
- Tests mark "has result = PASS" which is incorrect
- Need to verify semantic relevance, not just existence

### Improved Validation Logic

```php
// Instead of:
test("Query returns results", true, !empty($results));

// Use:
$topResult = $results[0] ?? null;
$isRelevant = $this->verifyDomainRelevance($query, $topResult);
test("Query returns relevant results", true, $isRelevant);

private function verifyDomainRelevance(string $query, ?array $result): bool {
    if (!$result) return false;
    
    $domainMap = [
        'wifi' => ['wifi', 'jaringan', 'internet'],
        'printer' => ['hardware', 'printer'],
        'email' => ['email'],
        'komputer' => ['hardware', 'komputer'],
        'internet' => ['internet'],
    ];
    
    $category = strtolower($result['category_name'] ?? '');
    
    foreach ($domainMap as $domain => $categories) {
        if (str_contains($query, $domain)) {
            foreach ($categories as $cat) {
                if (str_contains($category, $cat)) return true;
            }
            return false;
        }
    }
    
    return true; // Unknown domain, assume relevant
}
```

---

## 📋 IMPLEMENTATION CHECKLIST

- [ ] Add query expansion dictionary to PreprocessingService
- [ ] Implement hybrid scoring in ChatbotRetrievalService
- [ ] Add query coverage calculation
- [ ] Add negative domain penalty
- [ ] Add exact phrase boost calculation
- [ ] Add IT-specific stopword filtering
- [ ] Add query intent classification
- [ ] Add result confidence grouping
- [ ] Update validation tests for semantic relevance
- [ ] Test all combinations thoroughly

---

## 🎯 EXPECTED OUTCOMES

After implementation:
- **Higher retrieval accuracy** - Better matching of query intent
- **Reduced cross-domain pollution** - WiFi queries won't return printer articles
- **Better phrase matching** - Exact matches get proper priority
- **Improved user experience** - More relevant results, clearer confidence indicators

**Target Pass Rate:** 98%+ with semantic relevance verification