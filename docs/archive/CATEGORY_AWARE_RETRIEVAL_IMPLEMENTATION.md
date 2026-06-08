# Category-Aware Retrieval Implementation

## Overview
Implemented category-aware retrieval on top of the existing TF-IDF pipeline to fix retrieval accuracy issues where irrelevant articles were competing globally.

## Architecture

### New Service: DomainDetectionService
Located at: `app/Services/Chatbot/DomainDetectionService.php`

**Purpose**: Lightweight domain/category detection BEFORE TF-IDF retrieval runs.

**Key Features**:
1. **Domain Keyword Dictionary**: Maps domains (wifi, printer, email, etc.) to keywords
2. **Typo-Tolerant Matching**: Handles common typos (wfi → wifi, intenet → internet)
3. **Category Filtering**: Returns category IDs for article filtering
4. **Clean Suggestions**: Only returns verified domain/category data (no user data pollution)

### Modified Service: ChatbotRetrievalService
Updated to integrate domain detection into the retrieval pipeline.

**New Pipeline**:
```
Query → Domain Detection → Category Filtering → Typo Correction → 
Preprocessing → TF-IDF → Cosine Similarity → Context Boost → 
Diversification → Ranking → Response
```

## Implementation Details

### STEP 1: Domain Detection
```php
// DomainDetectionService::detectDomain($query)
// Returns:
[
    'detected' => true/false,
    'domain' => 'wifi' | 'printer' | 'email' | ...,
    'category_ids' => [1, 2, 3],
    'confidence' => 0.0 - 1.0
]
```

**Domain Mappings**:
| Domain | Keywords | Categories |
|--------|----------|------------|
| wifi | wifi, wi-fi, wireless, wlan, hotspot | wifi, jaringan, internet |
| internet | internet, inet, bandwidth, quota | internet, jaringan |
| jaringan | jaringan, network, lan, ethernet | jaringan, network, internet |
| printer | printer, cetak, epson, canon, tinta | printer, hardware |
| komputer | komputer, pc, laptop, notebook | komputer, hardware |
| email | email, gmail, outlook, mail | email, komunikasi |
| website | website, web, browser, chrome | website, internet |
| akun | akun, login, password, register | akun, authentication |
| sistem | sistem, aplikasi, software | sistem, umum |

### STEP 2: Category Filtering
```php
// ChatbotRetrievalService::getPublishedArticles($categoryIds)
// If domain detected: only retrieve from relevant categories
// If no domain detected: retrieve from all categories (fallback)
```

**Example**:
- Query: "wifi lemot" → Domain: wifi → Categories: [wifi, jaringan, internet]
- Only articles from these categories compete in TF-IDF ranking
- Computer/printer/BSOD articles are EXCLUDED from ranking

### STEP 3: Fallback for Unknown Domains
If category-filtered search returns no results:
1. Fall back to global search (all articles)
2. Apply stricter threshold filtering
3. Log fallback for debugging

### STEP 4: Generic Term Suppression
Generic terms are already suppressed via:
- `calculateTermWeightAdjustment()`: Reduces weight of "lemot", "error", etc.
- `GENERIC_TERM_WEIGHT = 0.5`: Generic terms get 50% weight
- `DOMAIN_TERM_WEIGHT = 2.5`: Domain terms get 250% weight

### STEP 5: Clean Domain Sources
`DomainDetectionService::getCleanDomainSuggestions()` returns ONLY:
- Verified domain keywords from the dictionary
- Verified categories from the database

NO user data, NO author names, NO arbitrary metadata.

### STEP 6: Article Diversification
```php
// ChatbotRetrievalService::diversifyResults($similarities, $documents, $queryTokens)
// Penalties applied:
// - Same category: 15% penalty per duplicate
// - Similar title patterns: 10% additional penalty
```

**Example**: If "komputer" query returns multiple BSOD articles:
- 1st BSOD article: full similarity
- 2nd BSOD article: -15% similarity
- 3rd BSOD article: -30% similarity

This promotes variety in results.

### STEP 7: Failed Query Escalation
Updated `formatResponse()` to include escalation options:
- Low confidence → show contact button
- No results → show escalation message
- Multiple failures → suggest ticket creation

## Expected Behavior

| Query | Domain Detected | Categories Filtered | Result |
|-------|-----------------|---------------------|--------|
| "wifi lemot" | wifi | wifi, jaringan, internet | Only wifi/network articles |
| "wfi lemot" | wifi (typo corrected) | wifi, jaringan, internet | Only wifi/network articles |
| "printer error" | printer | printer, hardware | Only printer articles |
| "email tidak masuk" | email | email, komunikasi | Only email articles |
| "komputer lemot" | komputer | komputer, hardware | Diversified computer articles |
| "kulkas samsung" | none | all (fallback) | No results + escalation |
| "halo" | none (greeting) | N/A | Greeting response only |

## Files Modified

1. **`app/Services/Chatbot/DomainDetectionService.php`** (NEW)
   - Domain keyword dictionary
   - Typo-tolerant matching
   - Category ID resolution
   - Clean suggestion generation

2. **`app/Services/Chatbot/ChatbotRetrievalService.php`** (MODIFIED)
   - Added `DomainDetectionService` dependency
   - Updated `retrieve()` to use domain detection
   - Updated `getPublishedArticles()` to accept category filter
   - Added `diversifyResults()` for result variety
   - Enhanced `formatResponse()` with escalation

3. **`resources/views/components/chatbot-widget.blade.php`** (PREVIOUSLY MODIFIED)
   - Frontend greeting isolation
   - Greeting bypass for all flows

## Testing Checklist

- [ ] "wifi lemot" → retrieves only wifi/network articles
- [ ] "wfi lemot" → typo corrected, same results as above
- [ ] "printer error" → retrieves only printer articles
- [ ] "komputer" → diversified results (not all BSOD)
- [ ] "kulkas samsung" → no forced retrieval + escalation
- [ ] "halo" → greeting only, no retrieval
- [ ] Category suggestions → no duplicates, no invalid data

## Academic Validity

This implementation:
- ✅ Maintains TF-IDF + Cosine Similarity as core algorithm
- ✅ Is fully deterministic and explainable
- ✅ Does NOT replace the retrieval algorithm
- ✅ Adds pre-filtering layer for better accuracy
- ✅ Suitable for academic research on hybrid retrieval systems