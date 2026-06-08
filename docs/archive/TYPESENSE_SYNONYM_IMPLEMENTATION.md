# Typesense Native Synonym Implementation

## Overview

This implementation adds native Typesense Synonym Sets to improve chatbot intent understanding. Instead of relying on manual phrase matching, the system now uses Typesense's built-in synonym feature to treat related terms as equivalent during search.

## Problem Solved

**Before:** Queries like "wifi gagal konek" or "internet tidak connect" would fail because the exact wording didn't match indexed content.

**After:** These queries now correctly match articles about connectivity issues because "konek", "connect", "terhubung", etc. are treated as synonyms.

## Implementation Details

### 1. Intent-Level Synonym Sets

Small, focused synonym groups organized by intent:

| Intent | Terms |
|--------|-------|
| **connectivity** | connect, konek, terhubung, tersambung, online, connection, koneksi, sambung, nyambung |
| **security** | virus, malware, trojan, ransomware, spyware, adware, worm, phishing |
| **printing** | print, printer, cetak, ngeprint, printing, mencetak, percetakan |
| **authentication** | login, signin, sign-in, masuk akun, log in, log-in, masuk, sign up, signup, register, daftar |
| **network** | wifi, internet, jaringan, network, lan, wireless, nirkabel, router, modem, access point, hotspot |
| **failure** | gagal, error, gagal konek, tidak bisa, ga bisa, gak bisa, tidak connect, tidak terhubung, masalah, issue, kendala |
| **speed** | lambat, slow, lemot, speed, kecepatan, bandwidth, lag, lagging, buffering |
| **email** | email, surel, mail, surat elektronik, gmail, outlook, yahoo mail |

### 2. Code Changes

#### `app/Services/Chatbot/TypesenseService.php`

Added the following methods:

- `getIntentSynonymSets()` - Returns all defined synonym sets
- `createSynonym(string $synonymId, array $synonyms)` - Create/update a single synonym set
- `createAllSynonyms()` - Create all intent synonym sets in Typesense
- `getSynonym(string $synonymId)` - Retrieve a specific synonym set
- `getAllSynonyms()` - Retrieve all synonym sets from Typesense
- `deleteSynonym(string $synonymId)` - Delete a specific synonym set
- `deleteAllSynonyms()` - Delete all synonym sets
- `matchSynonymIntents(string $query)` - Check which intents a query matches

#### `app/Console/Commands/SetupTypesense.php`

Updated to automatically create synonym sets during setup:

```bash
php artisan typesense:setup --reindex
```

The command now:
1. Creates/updates the articles collection
2. Creates all intent-level synonym sets
3. Shows a summary table of created synonyms
4. Optionally reindexes all articles

### 3. How Typesense Synonyms Work

Typesense synonyms are **bidirectional**. When a synonym set like `["connect", "konek", "terhubung"]` is defined:

- A search for "connect" will also match documents containing "konek" or "terhubung"
- A search for "konek" will also match documents containing "connect" or "terhubung"
- A search for "terhubung" will also match documents containing "connect" or "konek"

This happens at the Typesense engine level, so it's fast and doesn't require query expansion in application code.

## Usage

### Running Setup

```bash
# Full setup with reindex
php artisan typesense:setup --reindex

# Just setup collection and synonyms (no reindex)
php artisan typesense:setup
```

### Testing

```bash
php test_synonyms.php
```

### Programmatic Access

```php
use App\Services\Chatbot\TypesenseService;

$typesense = new TypesenseService();

// Get all synonym sets
$synonyms = $typesense->getIntentSynonymSets();

// Check which intents a query matches
$matched = $typesense->matchSynonymIntents('wifi gagal konek');
// Returns: ['connectivity' => [...], 'network' => [...], 'failure' => [...]]

// Create a custom synonym
$typesense->createSynonym('custom_intent', ['term1', 'term2', 'term3']);
```

## Expected Results

| User Query | Matched Intents | Expected Article |
|------------|-----------------|------------------|
| "wifi gagal konek" | connectivity, network, failure | Wifi connectivity article |
| "internet tidak connect" | connectivity, network, failure | Wifi connectivity article |
| "printer ga bisa print" | printing, failure | Printer troubleshooting article |
| "virus malware trojan" | security | Security/antivirus article |
| "login gagal masuk akun" | authentication, failure | Login troubleshooting article |
| "email tidak bisa kirim" | email, failure | Email troubleshooting article |
| "internet lambat buffering" | network, speed | Network speed article |

## Adding New Synonym Sets

To add a new intent synonym set, edit `app/Services/Chatbot/TypesenseService.php`:

```php
private array $intentSynonymSets = [
    // ... existing sets ...
    
    'new_intent' => [
        'term1', 'term2', 'term3',
    ],
];
```

Then run:
```bash
php artisan typesense:setup
```

## Important Notes

1. **Keep synonym groups SMALL and INTENT-FOCUSED** - Don't create giant manual dictionaries
2. Synonyms are loaded automatically during `typesense:setup`
3. The collection must exist before synonyms can be created
4. Synonyms are bidirectional - all terms in a set are treated as equivalent
5. Multi-word synonyms like "gagal konek" and "masuk akun" are supported

## Files Modified

1. `app/Services/Chatbot/TypesenseService.php` - Added synonym management methods
2. `app/Console/Commands/SetupTypesense.php` - Integrated synonym creation into setup

## Files Created

1. `test_synonyms.php` - Test script for synonym functionality
2. `TYPESENSE_SYNONYM_IMPLEMENTATION.md` - This documentation