# IMPORTANT TOKEN WEIGHTING AND OOD DETECTION FIX

## Problem Statement

Queries like:
- "pc ku kena virus"
- "docker di laptop error"

Were returning generic hardware/performance articles like:
- thermal paste
- laptop lemot

Additionally, queries like "virus" and "virussss" were sometimes being rejected as non-IT queries.

## Root Cause

1. **Generic device words were treated as IMPORTANT TOKENS**: `pc`, `laptop`, `komputer`, `desktop`, `notebook`, `error` were boosting generic hardware articles, overpowering the REAL intent keywords like `virus`, `docker`, `ransomware`.

2. **OOD detection was too aggressive**: Queries containing important technical tokens like `virus` were being rejected as out-of-domain.

## Changes Made

### 1. AdvancedRetrievalService.php

#### Removed Generic Tokens from Low Priority Terms
- Removed `pc`, `laptop`, `komputer`, `desktop`, `notebook`, `error` from `$lowPriorityTerms`
- These generic device words should NOT influence ranking significantly

#### Added Important Domain Tokens
Created `$importantDomainTokens` array containing TRUE intent tokens:
- **Security tokens**: `virus`, `malware`, `ransomware`, `trojan`, `spyware`, `phishing`, `antivirus`
- **DevOps tokens**: `docker`, `kubernetes`, `k8s`, `container`
- **Network tokens**: `wifi`, `jaringan`, `network`, `vpn`, `router`, `modem`
- **Hardware peripheral tokens**: `printer`, `scanner`
- **Data tokens**: `database`, `mysql`, `postgresql`, `mongodb`, `sql`
- **Communication tokens**: `email`, `gmail`, `outlook`
- **Web tokens**: `website`, `browser`, `chrome`, `firefox`
- **Account tokens**: `akun`, `login`, `password`
- **Specific issue tokens**: `lemot`, `bsod`, `hang`, `crash`, `freeze`

#### Added Security Priority Tokens
Created `$securityPriorityTokens` array for security-related terms that should get STRONG boost.

#### Added Security Priority Boost
- Added `hasSecurityIntent()` method to detect if query contains security tokens
- Added `isSecurityDocument()` method to check if document is security-related
- Modified `hybridRanking()` to apply +0.35 boost to security articles when query has security intent
- This ensures security articles override generic hardware articles for security queries

#### Updated isDomainSpecificTerm()
- Now uses `$importantDomainTokens` array instead of hardcoded list
- Ensures only true intent tokens get boosted in ranking

### 2. DomainDetectionService.php

#### Added Never-Reject Tokens
Created `$neverRejectTokens` array containing critical IT terms that should NEVER cause rejection:
- Same tokens as `$importantDomainTokens` (security, DevOps, network, etc.)

#### Added containsNeverRejectToken() Method
- Checks if query tokens contain any "never reject" token
- Handles exact matches AND partial matches (e.g., "virussss" matches "virus")
- Uses substring matching to handle typos and repeated characters

#### Updated evaluateOutOfDomain() Method
- Now accepts `$tokens` parameter
- First checks if query contains any "never reject" token
- If found, ALWAYS returns `false` (IN-DOMAIN) regardless of other factors
- This ensures queries like "virus", "docker", "wifi" are NEVER rejected

## Expected Results

After these changes:

| Query | Expected Result |
|-------|-----------------|
| "pc ku kena virus" | Virus article (NOT thermal paste) |
| "docker di laptop error" | Docker article (NOT laptop lemot) |
| "virus" | Virus article (NOT rejected) |
| "virussss" | Virus article (NOT rejected - handles typo) |
| "wifi lemot" | WiFi article |
| "printer tidak respon" | Printer article |

## Files Modified

1. `app/Services/Chatbot/AdvancedRetrievalService.php`
   - Removed generic tokens from `$lowPriorityTerms`
   - Added `$importantDomainTokens` array
   - Added `$securityPriorityTokens` array
   - Added `hasSecurityIntent()` method
   - Added `isSecurityDocument()` method
   - Updated `hybridRanking()` with security boost
   - Updated `isDomainSpecificTerm()` to use `$importantDomainTokens`

2. `app/Services/Chatbot/DomainDetectionService.php`
   - Added `$neverRejectTokens` array
   - Added `containsNeverRejectToken()` method
   - Updated `evaluateOutOfDomain()` to check for never-reject tokens

## Testing Recommendations

1. Test queries with security intent:
   - "pc ku kena virus" → Should return virus article
   - "laptop kena malware" → Should return malware article
   - "komputer kena ransomware" → Should return ransomware article

2. Test queries with DevOps intent:
   - "docker di laptop error" → Should return docker article
   - "kubernetes tidak jalan" → Should return kubernetes article

3. Test single technical tokens:
   - "virus" → Should return virus article (NOT rejected)
   - "virussss" → Should return virus article (NOT rejected)
   - "docker" → Should return docker article
   - "wifi" → Should return wifi article

4. Test that non-IT queries are still rejected:
   - "kucing" → Should be rejected
   - "rendang" → Should be rejected
   - "mobil balap" → Should be rejected