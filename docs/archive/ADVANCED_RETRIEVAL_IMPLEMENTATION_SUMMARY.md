# Advanced Retrieval Refinement Features - Implementation Summary

## Overview

This implementation adds five advanced retrieval refinement features to the helpdesk chatbot system:

1. **Multi-intent Splitting** - Split queries like "printer error dan wifi lemot" into separate intents
2. **Result Diversification** - Avoid repeated BSOD domination, diversify across categories
3. **Failure Escalation** - After repeated failures, offer Live Chat or Buat Tiket
4. **Conversation Memory** - Track conversation history for context
5. **Clarification Flow** - Guide users when queries are ambiguous

## Files Modified

### 1. `app/Services/Chatbot/AdvancedRetrievalService.php`

**New Features Added:**

#### Multi-intent Splitting
- `detectMultiIntent()` - Detects queries with "dan", "atau", "dengan", "serta", ","
- `multiIntentRetrieval()` - Retrieves results for each intent separately and merges them
- Example: "printer error dan wifi lemot" → ["printer error", "wifi lemot"]

#### Result Diversification
- Enhanced `diversifyResults()` with category quotas
- `MAX_RESULTS_PER_CATEGORY = 2` - Maximum 2 articles from same category
- Title pattern diversity to avoid BSOD domination
- Penalty system for repeated categories/title patterns

#### Failure Escalation
- Session-based failure memory (`SESSION_FAILURE_KEY`)
- `trackRetrievalResult()` - Tracks failed queries
- `shouldEscalate()` - Returns true after 3 consecutive failures
- `getEscalationResponse()` - Returns escalation buttons (Live Chat, Buat Tiket)
- `getFailureCount()` - Get current failure count for a query
- `clearFailureForQuery()` - Clear failure when success is achieved

#### Conversation Memory
- Session-based conversation tracking (`SESSION_CONVERSATION_KEY`)
- `storeConversationContext()` - Store interaction context
- `getRecentConversationContext()` - Retrieve recent context
- `clearConversationMemory()` - Clear conversation history

#### Clarification Flow
- `needsClarification()` - Detects ambiguous queries (e.g., "lemot", "error")
- `getClarificationSuggestions()` - Get domain-specific suggestions
- `getClarificationResponse()` - Return clarification question + suggestions
- Curated subtopics for each domain (wifi, printer, komputer, etc.)

### 2. `app/Http/Controllers/ChatbotController.php`

**Changes:**
- Switched from `ChatbotRetrievalService` to `AdvancedRetrievalService`
- Updated `getResponse()` to integrate all new features
- Added new endpoints:
  - `checkEscalation()` - Check if escalation is needed
  - `getClarification()` - Get clarification for ambiguous query
  - `getConversationHistory()` - Get recent conversation context
  - `clearConversation()` - Clear conversation history

## API Response Examples

### Multi-intent Query
```json
{
  "success": true,
  "response": "Saya menemukan artikel yang sangat relevan: **Printer Error** 😊",
  "articles": [...],
  "multi_intent": {
    "detected": true,
    "intents": ["printer error", "wifi lemot"]
  },
  "diversity": {
    "categories": 2,
    "is_diverse": true
  }
}
```

### Clarification Needed
```json
{
  "success": true,
  "needs_clarification": true,
  "clarification_question": "Bisa lebih spesifik? 😊",
  "suggestions": [
    {"id": "wifi_lemot", "label": "WiFi Lemot", "query": "wifi lemot"},
    {"id": "wifi_tidak_connect", "label": "WiFi Tidak Connect", "query": "wifi tidak connect"}
  ]
}
```

### Escalation Response
```json
{
  "success": false,
  "response": "Sepertinya saya belum menemukan solusi yang tepat 😔\n\nJangan khawatir, tim support kami siap membantu!",
  "should_escalate": true,
  "escalation_buttons": [
    {"label": "💬 Live Chat", "action": "contact_staff"},
    {"label": "📧 Buat Tiket", "action": "create_ticket"},
    {"label": "🔄 Coba Pertanyaan Lain", "action": "try_another"}
  ]
}
```

## Configuration Constants

```php
// Failure escalation
FAILURE_THRESHOLD = 3;           // Escalate after 3 failures
MAX_FAILURE_MEMORY = 10;         // Track up to 10 queries

// Diversification
MAX_RESULTS_PER_CATEGORY = 2;    // Max 2 articles per category

// Session keys
SESSION_FAILURE_KEY = 'chatbot_failure_memory';
SESSION_CONVERSATION_KEY = 'chatbot_conversation_memory';
```

## How It Works

### 1. Multi-intent Splitting Flow
```
Query: "printer error dan wifi lemot"
    ↓
detectMultiIntent() → ["printer error", "wifi lemot"]
    ↓
singleIntentRetrieval("printer error") → [articles...]
singleIntentRetrieval("wifi lemot") → [articles...]
    ↓
Merge results (deduplicate by ID)
    ↓
Return combined results
```

### 2. Failure Escalation Flow
```
Query → retrieve() → no results
    ↓
trackRetrievalResult() → increment failure count in session
    ↓
Next query → same domain → no results
    ↓
trackRetrievalResult() → increment failure count
    ↓
After 3 failures → shouldEscalate() returns true
    ↓
formatResponse() returns escalation buttons
```

### 3. Clarification Flow
```
Query: "lemot"
    ↓
needsClarification() → true (generic term only)
    ↓
getClarificationResponse() → suggestions for domain
    ↓
Return clarification question + suggestions
```

### 4. Diversification Flow
```
Ranked results: [BSOD1, BSOD2, BSOD3, WiFi1, WiFi2]
    ↓
diversifyResults() applies penalties:
- Category quota: max 2 per category
- Title pattern: penalty for repeated words
    ↓
Re-sort: [BSOD1, BSOD2, WiFi1, WiFi2, BSOD3]
    ↓
Return diversified results
```

## Testing

To test the features:

1. **Multi-intent**: Send query "printer error dan wifi lemot"
2. **Clarification**: Send query "lemot" or "error"
3. **Escalation**: Send same failing query 3+ times
4. **Diversification**: Check that results don't all come from same category

## Deterministic Behavior

All features are deterministic:
- Multi-intent splitting uses regex patterns
- Diversification uses consistent penalty values
- Failure memory is session-based (per-user)
- Clarification uses predefined patterns
- No AI/LLM generation involved

## Integration Notes

- The chatbot widget should handle new response fields:
  - `needs_clarification` - Show clarification suggestions
  - `should_escalate` - Show escalation buttons
  - `multi_intent` - Show that multiple intents were detected
  - `diversity` - Indicate result diversity

- Session keys used:
  - `chatbot_failure_memory` - Failure tracking
  - `chatbot_conversation_memory` - Conversation context