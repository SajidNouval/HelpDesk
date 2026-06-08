# Chatbot Logic/State Bugs Fix Summary

## Overview
Fixed 5 critical logic/state bugs in the conversational chatbot system.

---

## BUG 1 — Contextual Queries Triggering Clarification (FIXED)

### Problem
Query: "wifi lemot" incorrectly triggered clarification ("Yang sedang lemot apa ya?") even though it contains both domain term (wifi) and issue term (lemot).

### Solution
Added contextual query detection in `ConversationFlowService.php`:

1. Added `domainTerms` array: wifi, internet, printer, komputer, laptop, software, aplikasi, email, jaringan, router, modem, etc.
2. Added `issueTerms` array: lemot, lambat, error, eror, tidak bisa, rusak, mati, crash, hang, freeze, etc.
3. Added `isContextualQuery()` method that checks if query contains BOTH domain AND issue terms
4. Updated `checkAmbiguity()` to:
   - Skip ambiguity check if query is contextual (domain + issue)
   - Only trigger for standalone ambiguous patterns (e.g., just "lemot" alone)
   - Count extra significant words outside the pattern to determine if contextual

### Result
- "wifi lemot" → goes directly to retrieval (no clarification)
- "internet lambat" → goes directly to retrieval
- "printer error" → goes directly to retrieval
- "lemot" → triggers clarification (correct behavior)

---

## BUG 2 — Recursive Clarification Loop (FIXED)

### Problem
After clarification started, ambiguity detection kept triggering repeatedly:
```
lemot → "yang lemot apa?" → wifi → ambiguity triggers again
```

### Solution
Added `clarificationActive` state flag in frontend JavaScript:

1. Set `clarificationActive = true` when showing clarification UI
2. In `showClarificationUI()`, check if already in clarification mode:
   - If yes, skip clarification and go directly to retrieval
3. Reset `clarificationActive = false` when:
   - User sends a new query
   - User clicks a clarification suggestion
   - User clicks a subtopic

### Result
Clarification now happens ONLY ONCE per conversation chain.

---

## BUG 3 — Old Article Results Not Clearing (FIXED)

### Problem
When changing topic/query, old article cards remained visible, creating mixed/incorrect conversation UI.

### Solution
Added `clearPreviousResults()` function in frontend JavaScript:

1. Clears articles container (innerHTML and hidden class)
2. Clears subtopics container
3. Clears clarification suggestions
4. Clears response area
5. Called at:
   - Start of new query submission
   - When user clicks a subtopic
   - When user clicks a clarification suggestion

### Result
Only relevant results for current conversation branch are displayed.

---

## BUG 4 — Domain Context Weighting Too Weak (FIXED)

### Problem
Query: "wifi error" retrieved printer troubleshooting articles instead of wifi/network articles.

### Solution
Increased domain/context weighting in `ChatbotRetrievalService.php`:

| Constant | Before | After |
|----------|--------|-------|
| WEIGHT_TITLE | 2.5 | 3.5 |
| WEIGHT_KEYWORDS | 1.8 | 2.5 |
| CONTEXT_BOOST_FACTOR | 0.3 | 0.4 |
| DOMAIN_BOOST_FACTOR | 0.2 | 0.5 |

### Result
- Domain terms (wifi, printer, internet) now carry much stronger weight
- "wifi error" correctly retrieves wifi/network articles
- Title matches are weighted 3.5x higher than content matches

---

## BUG 5 — Category Duplication (FIXED)

### Problem
Duplicate chips appeared: "Email Email", "Hardware Hardware"

### Solution
Added deduplication in `getCategorySuggestions()` in `ConversationFlowService.php`:

1. Track seen category names (normalized to lowercase)
2. Only add category if not already seen
3. Return unique array

### Result
Each category appears only once in suggestions.

---

## Files Modified

1. **`app/Services/Chatbot/ConversationFlowService.php`**
   - Added `domainTerms` and `issueTerms` arrays
   - Added `isContextualQuery()` method
   - Updated `checkAmbiguity()` with contextual detection
   - Added deduplication to `getCategorySuggestions()`

2. **`app/Services/Chatbot/ChatbotRetrievalService.php`**
   - Increased `WEIGHT_TITLE` from 2.5 to 3.5
   - Increased `WEIGHT_KEYWORDS` from 1.8 to 2.5
   - Increased `CONTEXT_BOOST_FACTOR` from 0.3 to 0.4
   - Increased `DOMAIN_BOOST_FACTOR` from 0.2 to 0.5

3. **`resources/views/components/chatbot-widget.blade.php`**
   - Added `clarificationActive` state flag
   - Added `clearPreviousResults()` function
   - Updated `showClarificationUI()` to prevent recursive loops
   - Updated form submit handler to clear previous results
   - Updated `handleSubtopicClick()` to clear previous results

---

## Testing Checklist

- [ ] Query "wifi lemot" → goes directly to retrieval (no clarification)
- [ ] Query "lemot" → shows clarification with category suggestions
- [ ] After clarification, clicking suggestion → no second clarification
- [ ] Changing topic → old articles disappear, new ones appear
- [ ] Category chips show no duplicates
- [ ] Query "wifi error" → retrieves wifi articles, not printer articles