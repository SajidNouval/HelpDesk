# Query By Weights Implementation

## Problem
Relevant article titles were sometimes losing to articles with more generic content overlap. The search ranking needed to prioritize title matches over content matches.

## Solution
Updated Typesense search parameters to use `query_by_weights` with the following configuration:

### Changes Made

**File:** `app/Services/Chatbot/TypesenseService.php`

**Before:**
```php
'query_by' => 'title,keywords,content',
'query_by_weights' => '8,5,1',
```

**After:**
```php
'query_by' => 'title,keywords,category_name,content',
'query_by_weights' => '8,6,4,2',
```

### Weight Configuration

| Field | Weight | Priority |
|-------|--------|----------|
| title | 8 | Highest - Title matches are most relevant |
| keywords | 6 | High - Keywords are curated metadata |
| category_name | 4 | Medium - Category provides context |
| content | 2 | Lowest - Content is broad and generic |

### How It Works

1. **Title (weight=8):** Articles with query terms in the title get the highest score boost
2. **Keywords (weight=6):** Articles with matching keywords get a strong boost
3. **Category (weight=4):** Articles in relevant categories get a moderate boost
4. **Content (weight=2):** Content matches provide the baseline relevance

### Test Results

```
Testing query: 'virus'
  1   Cara Mengenali dan Menghapus Virus Komputer        [TITLE MATCH]
  2   Perbedaan Malware, Virus, Trojan, dan Ransomware   [TITLE MATCH]
  3   Cara Mengaktifkan Windows Defender dan Antivirus   [TITLE MATCH]

Testing query: 'docker'
  1   Docker Swarm                                       [TITLE MATCH] ✓ PASS

Testing query: 'printer offline'
  1   Cara Mengatasi Printer Offline dan Tidak Terdeteksi [TITLE MATCH] ✓ PASS
```

### Expected Behavior

Queries like "virus", "docker", "printer offline" now prioritize articles whose **TITLES** directly match the intent, rather than articles that merely contain the terms somewhere in their content.

### Additional Benefits

- **Better user experience:** Users see the most relevant articles first
- **Reduced noise:** Generic content matches are deprioritized
- **Category awareness:** Articles in relevant categories get a moderate boost
- **Maintained flexibility:** Content still contributes to relevance, just with lower weight

## Related Configuration

The Typesense collection schema (in `config/typesense.php`) already includes all required fields:
- `title` (string, searchable)
- `keywords` (string, searchable)
- `category_name` (string, facet/searchable)
- `content` (string, searchable)

No schema changes were required.