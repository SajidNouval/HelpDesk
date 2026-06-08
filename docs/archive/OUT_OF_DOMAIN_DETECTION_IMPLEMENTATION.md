# OUT-OF-DOMAIN DETECTION IMPLEMENTATION

## Problem Statement

Queries like "kucing", "rendang", "mobil balap" were returning unrelated IT articles instead of being politely rejected.

## Solution

Implemented OUT-OF-DOMAIN detection that runs BEFORE retrieval to detect whether a query belongs to the IT/support domain.

## Implementation Details

### 1. DomainDetectionService (`app/Services/Chatbot/DomainDetectionService.php`)

Added `detectOutOfDomain()` method that uses three criteria:

1. **Explicit OUT-OF-DOMAIN Keywords**: Immediate rejection for clearly non-IT terms
   - Food: kucing, rendang, nasi, etc.
   - Vehicles: mobil, motor, balap, etc.
   - Entertainment: film, musik, bola, etc.
   - Shopping: belanja, beli, jual, etc.
   - Health: sakit, dokter, obat, etc.

2. **IT Domain Vocabulary Matching**: Check if query contains IT-related terms
   - Core IT: wifi, internet, printer, email, website, etc.
   - OS/Platforms: windows, linux, android, etc.
   - Security: virus, malware, ransomware, etc.
   - Hardware: laptop, router, driver, etc.

3. **Vocabulary Overlap Scoring**: Calculate ratio of IT tokens to total meaningful tokens

### 2. Evaluation Logic

```php
// If no IT tokens found -> OUT-OF-DOMAIN
// If has IT tokens AND good vocabulary overlap -> IN-DOMAIN
// If vocabulary overlap is very low AND no domain confidence -> OUT-OF-DOMAIN
// Better to accept a borderline IT query than reject a valid one
```

### 3. AdvancedRetrievalService Integration

Modified `retrieve()` method to check OUT-OF-DOMAIN BEFORE any retrieval:

```php
// Check if query is outside IT/support domain
// If so, return early with rejection message - DO NOT fallback to IT articles
$outOfDomainCheck = $this->domainDetector->detectOutOfDomain($query);

if ($outOfDomainCheck['is_out_of_domain']) {
    return $this->outOfDomainResult($query);
}
```

### 4. Response Format

For OUT-OF-DOMAIN queries:
```php
[
    'success' => false,
    'response' => 'Maaf, saya hanya dapat membantu masalah terkait IT.',
    'articles' => [],
    'show_contact_button' => false,
    'is_out_of_domain' => true,
    'confidence' => 'none',
]
```

## Test Results

### Non-IT Queries (All Correctly Rejected)
- kucing ✅ REJECTED
- rendang ✅ REJECTED
- mobil balap ✅ REJECTED
- nasi goreng ✅ REJECTED
- film action ✅ REJECTED
- sepak bola ✅ REJECTED
- resep masakan ✅ REJECTED
- belanja online ✅ REJECTED
- liburan ke bali ✅ REJECTED
- sakit kepala ✅ REJECTED

### IT Queries (All Correctly Accepted)
- wifi lemot ✅ ACCEPTED
- printer tidak bisa print ✅ ACCEPTED
- komputer sering hang ✅ ACCEPTED
- email tidak masuk ✅ ACCEPTED
- internet putus-putus ✅ ACCEPTED
- virus di laptop ✅ ACCEPTED
- cara install windows ✅ ACCEPTED
- website tidak bisa diakses ✅ ACCEPTED
- lupa password akun ✅ ACCEPTED
- aplikasi error ✅ ACCEPTED

## Files Modified

1. `app/Services/Chatbot/DomainDetectionService.php` - Added OUT-OF-DOMAIN detection logic
2. `app/Services/Chatbot/AdvancedRetrievalService.php` - Integrated detection into retrieval pipeline
3. `test_out_of_domain_detection.php` - Test script for validation

## Key Design Decisions

1. **Detection BEFORE Retrieval**: OUT-OF-DOMAIN check happens before any TF-IDF retrieval to avoid wasting resources and prevent unrelated article results.

2. **Lenient for Borderline Cases**: Better to accept a borderline IT query than reject a valid one. The system uses multiple criteria and accepts if ANY criterion indicates IT domain.

3. **No Fallback to IT Articles**: When a query is OUT-OF-DOMAIN, we return an empty result with a polite message instead of forcing unrelated IT articles.

4. **Curated Static Lists Only**: All domain keywords and OUT-OF-DOMAIN terms come from curated static lists - no arbitrary article tokens or user data.