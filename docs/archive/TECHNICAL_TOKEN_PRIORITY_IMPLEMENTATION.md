# TECHNICAL TOKEN PRIORITY RETRIEVAL IMPLEMENTATION

## Problem Statement

The retrieval system was failing on technical-specific queries like:
- `ransomware`
- `virus`
- `trojan`
- `malware`

These queries were retrieving generic articles like "komputer lemot" instead of exact matching security articles.

## Root Causes

1. **Overly aggressive stemming**: Technical terms like "ransomware" were being stemmed to "ransomwar", destroying their meaning
2. **Bad preprocessing**: No distinction between technical and generic terms
3. **Weak technical-token weighting**: Security terms had same weight as generic terms
4. **Poor exact-token priority**: No special handling for exact technical term matches
5. **Generic TF-IDF dominance**: Broad articles with repeated generic terms dominated scores

## Solution Implemented

### Part 1: Protected Technical Token Dictionary

Created a comprehensive dictionary of ~150 technical tokens that MUST NEVER be stemmed:

**File**: `app/Services/Chatbot/PreprocessingService.php`

```php
private array $protectedTechnicalTokens = [
    // Security/Malware terms (CRITICAL)
    'ransomware', 'malware', 'virus', 'trojan', 'spyware', 'adware',
    'worm', 'rootkit', 'keylogger', 'phishing', 'backdoor', 'exploit',
    
    // Network/Protocol terms
    'vpn', 'wifi', 'http', 'https', 'ftp', 'ssh', 'ssl', 'tls', 'dns',
    
    // Hardware/Device terms
    'router', 'switch', 'printer', 'scanner', 'monitor', 'bluetooth',
    
    // Software/Platform terms
    'windows', 'linux', 'macos', 'android', 'ios', 'gmail', 'outlook',
    
    // Common technical terms
    'api', 'sdk', 'docker', 'kubernetes', 'json', 'xml', 'php', 'python',
];
```

### Part 2: Protected Token Stemming Logic

Modified the `stem()` method to check if a token is protected before stemming:

```php
private function stem(string $word): string
{
    // CRITICAL: Check if this is a protected technical token
    if ($this->isProtectedTechnicalToken($word)) {
        return $word; // DO NOT STEM - return as-is
    }
    
    // ... normal stemming logic ...
}
```

### Part 3: Security Token Exact Match Boost

Added massive boost (+12.0) for security terms in title:

```php
private const SECURITY_TOKEN_EXACT_BOOST = 10.0;  // MASSIVE boost
private const TECHNICAL_EXACT_TITLE_BOOST = 12.0; // For title matches

private function calculateSecurityTokenBoost(...): float
{
    $securityTerms = ['ransomware', 'malware', 'virus', 'trojan', ...];
    
    foreach ($securityTerms as $term) {
        if ($queryHasTerm && $titleHasTerm) {
            $boost += self::TECHNICAL_EXACT_TITLE_BOOST; // +12.0
        }
        // ... keyword and content boosts ...
    }
}
```

### Part 4: Technical Token Exact Match Boost

Added boost for all protected technical tokens:

```php
private function calculateTechnicalExactBoost(...): float
{
    $protectedTokens = $this->preprocessor->getProtectedTechnicalTokens();
    
    foreach ($protectedTokens as $token) {
        if ($queryHasToken) {
            $boost += 2.0 * ($locationCount / 3); // Up to +2.0 per token
        }
    }
}
```

### Part 5: Genericity Penalty

Added penalty for overly generic/broad articles:

```php
private const GENERICITY_PENALTY = 0.3;
private const REPEATED_TERM_PENALTY = 0.5;

private function calculateGenericityPenalty(...): float
{
    $genericPatterns = ['cara mengatasi', 'solusi lengkap', ...];
    
    // Penalize articles matching generic patterns
    // when query has specific technical terms
    if ($matchesGenericPattern && !$hasSpecificTerms) {
        $penalty *= self::GENERICITY_PENALTY;
    }
}
```

### Part 6: Integrated Scoring Formula

Updated the main scoring formula to include all new boosts:

```php
$combinedBoost = $domainBoost + $coverageBoost + $exactPhraseBoost 
               + $titleMatchBoost + $domainFirstBoost 
               + $securityBoost + $technicalExactBoost;

$boostedSimilarity = ($baseSimilarity * $genericPenalty * $genericityPenalty) 
                   + $combinedBoost;

$finalSimilarity = $boostedSimilarity * $domainPenalty * $crossDomainPenalty;
```

## Files Modified

1. **`app/Services/Chatbot/PreprocessingService.php`**
   - Added `$protectedTechnicalTokens` dictionary (~150 terms)
   - Modified `stem()` to skip protected tokens
   - Added `isProtectedTechnicalToken()` method
   - Added `getProtectedTechnicalTokens()` method

2. **`app/Services/Chatbot/ChatbotRetrievalService.php`**
   - Added `SECURITY_TOKEN_EXACT_BOOST` constant (+10.0)
   - Added `TECHNICAL_EXACT_TITLE_BOOST` constant (+12.0)
   - Added `GENERICITY_PENALTY` constant (0.3)
   - Added `REPEATED_TERM_PENALTY` constant (0.5)
   - Added `calculateSecurityTokenBoost()` method
   - Added `calculateTechnicalExactBoost()` method
   - Added `calculateGenericityPenalty()` method
   - Updated `calculateSimilaritiesWithBoost()` to integrate new methods

## Test Results

All tests pass:

```
✓ PASS: 'ransomware' -> 'ransomware' (expected: 'ransomware')
✓ PASS: 'malware' -> 'malware' (expected: 'malware')
✓ PASS: 'virus' -> 'virus' (expected: 'virus')
✓ PASS: 'trojan' -> 'trojan' (expected: 'trojan')
✓ PASS: 'vpn' -> 'vpn' (expected: 'vpn')
✓ PASS: 'gmail' -> 'gmail' (expected: 'gmail')
✓ PASS: 'printer' -> 'printer' (expected: 'printer')
✓ PASS: 'wifi' -> 'wifi' (expected: 'wifi')
```

## Expected Behavior After Fix

| Query | Expected Result |
|-------|-----------------|
| `ransomware` | Ransomware article FIRST |
| `virus` | Virus/security article FIRST |
| `trojan` | Trojan article FIRST |
| `malware` | Malware article FIRST |
| `vpn` | VPN article FIRST |
| `gmail error` | Gmail article FIRST |
| `printer offline` | Printer article FIRST |

**NOT**:
- "komputer lemot" articles for security queries
- "internet lemot" articles for printer queries

## Verification

Run the test script:
```bash
php test_technical_token_priority.php
```

Clear cache and test the chatbot:
```bash
php artisan cache:clear
# Then test queries in the chatbot widget