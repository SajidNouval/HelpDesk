@props(['show' => true])

<div id="chatbot-widget" class="fixed bottom-4 right-4 w-96 h-[600px] bg-white rounded-2xl shadow-2xl flex flex-col z-50 hidden">
    <!-- Header -->
    <div class="bg-gradient-to-r from-red-600 to-red-700 text-white p-4 rounded-t-2xl flex justify-between items-center">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5z"></path>
                    <path d="M15 7H5"></path>
                </svg>
            </div>
            <div>
                <h3 class="font-bold text-sm">SiMinfo Assistant</h3>
                <p class="text-xs text-red-200">Bantuan Otomatis</p>
            </div>
        </div>
        <button id="chatbot-close" class="text-white hover:bg-white/20 p-2 rounded-xl transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <!-- Chat Container -->
    <div id="chatbot-messages" class="flex-1 overflow-y-auto p-4 bg-gray-50 space-y-4">
        <!-- Initial Greeting - will be populated dynamically -->
        <div id="chatbot-greeting" class="space-y-3" data-chatbot-greeting>
            <!-- Greeting message -->
            <div class="flex items-start">
                <div class="bg-red-100 text-red-900 rounded-2xl rounded-tl-md p-3 max-w-xs" data-chatbot-greeting-message>
                    <p class="text-sm font-semibold">Halo! 👋</p>
                    <p class="text-sm mt-1">Ada yang bisa saya bantu?</p>
                </div>
            </div>
            <!-- Category chips -->
            <div id="category-chips" class="flex flex-wrap gap-2 pl-2" data-chatbot-categories>
                <!-- Categories will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Clarification Container -->
    <div id="chatbot-clarification" class="hidden px-4 py-3 bg-white border-t border-gray-200">
        <p id="clarification-question" class="text-sm text-gray-700 font-medium mb-3"></p>
        <div id="clarification-suggestions" class="flex flex-wrap gap-2">
            <!-- Clarification suggestions will be loaded dynamically -->
        </div>
    </div>

    <!-- Subtopic suggestions container -->
    <div id="chatbot-subtopics" class="hidden px-4 py-3 bg-white border-t border-gray-200 max-h-32 overflow-y-auto">
        <p id="subtopic-question" class="text-xs text-gray-600 font-semibold mb-2"></p>
        <div id="subtopics-list" class="flex flex-wrap gap-2">
            <!-- Subtopics will be loaded dynamically -->
        </div>
    </div>

    <!-- Search Suggestions Dropdown -->
    <div id="search-suggestions" class="hidden absolute bottom-20 left-4 right-4 bg-white rounded-xl shadow-lg border border-gray-200 max-h-48 overflow-y-auto z-10">
        <div id="search-suggestions-list" class="py-2">
            <!-- Search suggestions will be loaded dynamically -->
        </div>
    </div>

    <!-- Response Area -->
    <div id="chatbot-response" class="hidden px-4 py-3 bg-white border-t border-gray-200 max-h-40 overflow-y-auto">
        <!-- Bot response akan ditampilkan di sini -->
        <div id="contact-button-container" class="hidden mt-3">
            <button id="contact-staff-btn" class="bg-green-600 text-white text-xs font-semibold py-2 px-4 rounded-xl hover:bg-green-700 transition">
                Buat Tiket untuk Bantuan Lebih Lanjut
            </button>
        </div>
    </div>

    <!-- Articles Section -->
    <div id="chatbot-articles" class="hidden px-4 py-3 bg-white border-t border-gray-200 max-h-48 overflow-y-auto">
        <p class="text-xs text-gray-600 font-semibold mb-2">📚 Artikel Terkait:</p>
        <div id="articles-list" class="space-y-2">
            <!-- Articles akan ditampilkan di sini -->
        </div>
    </div>

    <!-- Ticket Creation Form -->
    <div id="chatbot-form" class="hidden px-4 py-4 bg-white border-t border-gray-200 max-h-48 overflow-y-auto">
        <p class="text-xs text-gray-600 font-semibold mb-3">Buat Tiket untuk Bantuan Lebih Lanjut</p>
        <form id="ticket-form" class="space-y-3">
            <input type="hidden" name="message" id="form-message">
            
            <input type="text" name="title" placeholder="Judul masalah" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition" required>
            
            <select name="category_id" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition" required>
                <option value="">Pilih Kategori</option>
                @foreach($categories ?? [] as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            
            <input type="email" name="email" placeholder="Email Anda (opsional)" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
            
            <textarea name="message" placeholder="Jelaskan masalah Anda secara detail" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition resize-none" required></textarea>
            
            <div class="flex gap-2">
                <x-primary-button type="submit" class="flex-1 text-xs py-2">Buat Tiket</x-primary-button>
                <x-secondary-button type="button" id="chatbot-cancel-btn" class="flex-1 text-xs py-2">Batal</x-secondary-button>
            </div>
        </form>
    </div>

    <!-- Input Area -->
    <div class="border-t border-gray-200 p-4 bg-white rounded-b-2xl">
        <form id="chatbot-form-input" class="flex gap-2">
            <div class="flex-1 relative">
                <input 
                    type="text" 
                    id="chatbot-input" 
                    placeholder="Ketik pertanyaan Anda..." 
                    class="w-full px-4 py-3 pr-10 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                    autocomplete="off"
                >
                <!-- Clear button -->
                <button type="button" id="clear-input" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 hidden">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
            <x-primary-button type="submit" class="px-4 py-2 flex items-center justify-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5.951-1.488 5.951 1.488a1 1 0 001.169-1.409l-7-14z"></path>
                </svg>
            </x-primary-button>
        </form>
    </div>
</div>

<!-- Floating Button -->
<button id="chatbot-toggle" class="fixed bottom-4 right-4 bg-red-600 hover:bg-red-700 text-white rounded-full p-4 shadow-lg transition transform hover:scale-105 z-50">
    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
        <path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5z"></path>
        <path d="M15 7H5"></path>
    </svg>
</button>

<style>
    #chatbot-widget.show {
        display: flex;
    }
    
    #chatbot-toggle.hide {
        display: none;
    }

    .bot-message {
        @apply text-left;
    }

    .user-message {
        @apply text-right;
    }

    .message-bubble {
        @apply rounded-2xl p-3 max-w-xs text-sm break-words;
    }

    .bot-bubble {
        @apply bg-red-100 text-red-900 rounded-tl-md;
    }

    .user-bubble {
        @apply bg-red-600 text-white rounded-tr-md;
    }

    .category-chip {
        @apply px-4 py-2 bg-red-50 text-red-700 text-xs font-medium rounded-full hover:bg-red-100 transition-all duration-200 cursor-pointer border border-red-200;
    }

    .category-chip:hover {
        @apply bg-red-100 border-red-300 transform scale-105;
    }

    .suggestion-chip {
        @apply px-3 py-1.5 bg-white text-gray-700 text-xs rounded-lg hover:bg-red-50 transition-all duration-200 cursor-pointer border border-gray-200;
    }

    .suggestion-chip:hover {
        @apply border-red-300 text-red-700;
    }

    .typing-dot {
        width: 6px;
        height: 6px;
        background: #9ca3af;
        border-radius: 50%;
        animation: typingBounce 1.4s infinite ease-in-out both;
    }
    .typing-dot:nth-child(1) { animation-delay: -0.32s; }
    .typing-dot:nth-child(2) { animation-delay: -0.16s; }
    @keyframes typingBounce {
        0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
        40% { transform: scale(1); opacity: 1; }
    }

    /* Custom scrollbar */
    #chatbot-messages::-webkit-scrollbar,
    #chatbot-articles::-webkit-scrollbar,
    #chatbot-subtopics::-webkit-scrollbar {
        width: 6px;
    }

    #chatbot-messages::-webkit-scrollbar-track,
    #chatbot-articles::-webkit-scrollbar-track,
    #chatbot-subtopics::-webkit-scrollbar-track {
        background: transparent;
    }

    #chatbot-messages::-webkit-scrollbar-thumb,
    #chatbot-articles::-webkit-scrollbar-thumb,
    #chatbot-subtopics::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 3px;
    }

    #chatbot-messages::-webkit-scrollbar-thumb:hover,
    #chatbot-articles::-webkit-scrollbar-thumb:hover,
    #chatbot-subtopics::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const widget = document.getElementById('chatbot-widget');
    const toggle = document.getElementById('chatbot-toggle');
    const closeBtn = document.getElementById('chatbot-close');
    const input = document.getElementById('chatbot-input');
    const clearInputBtn = document.getElementById('clear-input');
    const form = document.getElementById('chatbot-form-input');
    const messagesContainer = document.getElementById('chatbot-messages');
    const responseContainer = document.getElementById('chatbot-response');
    const articlesContainer = document.getElementById('chatbot-articles');
    const formContainer = document.getElementById('chatbot-form');
    const ticketForm = document.getElementById('ticket-form');
    const cancelTicketBtn = document.getElementById('chatbot-cancel-btn');
    const contactButtonContainer = document.getElementById('contact-button-container');
    const contactStaffBtn = document.getElementById('contact-staff-btn');
    const clarificationContainer = document.getElementById('chatbot-clarification');
    const clarificationQuestion = document.getElementById('clarification-question');
    const clarificationSuggestions = document.getElementById('clarification-suggestions');
    const subtopicsContainer = document.getElementById('chatbot-subtopics');
    const subtopicQuestion = document.getElementById('subtopic-question');
    const subtopicsList = document.getElementById('subtopics-list');
    const searchSuggestionsContainer = document.getElementById('search-suggestions');
    const searchSuggestionsList = document.getElementById('search-suggestions-list');

    let searchDebounceTimer = null;
    let currentContext = null;
    let clarificationActive = false; // BUG FIX 2: Track if clarification is active

    // Toggle widget
    toggle.addEventListener('click', () => {
        console.log('[CHATBOT] Toggle clicked');
        widget.classList.toggle('show');
        toggle.classList.toggle('hide');
        if (widget.classList.contains('show')) {
            console.log('[CHATBOT] Widget opened, calling loadGreeting');
            loadGreeting();
        }
    });

    closeBtn.addEventListener('click', () => {
        widget.classList.remove('show');
        toggle.classList.remove('hide');
    });

    // Clear input
    clearInputBtn.addEventListener('click', () => {
        input.value = '';
        clearInputBtn.classList.add('hidden');
        searchSuggestionsContainer.classList.add('hidden');
        input.focus();
    });

    // Show/hide clear button based on input
    input.addEventListener('input', () => {
        clearInputBtn.classList.toggle('hidden', input.value.length === 0);
        
        // Debounced search suggestions
        clearTimeout(searchDebounceTimer);
        if (input.value.length >= 2) {
            searchDebounceTimer = setTimeout(() => {
                loadSearchSuggestions(input.value);
            }, 300);
        } else {
            searchSuggestionsContainer.classList.add('hidden');
        }
    });

    // PART 12: Greeting isolation - queries that are greetings should bypass all flows
    // and clear all previous state (articles, recommendations, chips, escalation UI)
    function isGreetingQuery(message) {
        const greetings = ['halo', 'hai', 'hello', 'hi', 'pagi', 'siang', 'sore', 'malam', 'assalamualaikum', 'permisi'];
        const lowerMessage = message.toLowerCase().trim();
        return greetings.some(g => lowerMessage === g || lowerMessage.startsWith(g + ' ') || lowerMessage.endsWith(' ' + g));
    }

    function getGreetingResponse() {
        const hour = new Date().getHours();
        if (hour < 11) return 'Selamat pagi! 👋 Ada yang bisa saya bantu?';
        if (hour < 15) return 'Selamat siang! 👋 Silakan tanyakan sesuatu.';
        if (hour < 18) return 'Selamat sore! 👋 Ada yang bisa saya bantu?';
        return 'Selamat malam! 👋 Silakan tanyakan sesuatu.';
    }
    
    // PART 12: Reset to greeting state - clears ALL previous state
    function resetToGreeting() {
        // Clear article cards
        const articlesList = document.getElementById('articles-list');
        if (articlesList) {
            articlesList.innerHTML = '';
        }
        const articlesContainer = document.getElementById('chatbot-articles');
        if (articlesContainer) {
            articlesContainer.classList.add('hidden');
        }
        
        // Clear subtopics
        const subtopicsList = document.getElementById('subtopics-list');
        if (subtopicsList) {
            subtopicsList.innerHTML = '';
        }
        const subtopicsCont = document.getElementById('chatbot-subtopics');
        if (subtopicsCont) {
            subtopicsCont.classList.add('hidden');
        }
        
        // Clear clarification
        const clarifSuggestions = document.getElementById('clarification-suggestions');
        if (clarifSuggestions) {
            clarifSuggestions.innerHTML = '';
        }
        const clarifCont = document.getElementById('chatbot-clarification');
        if (clarifCont) {
            clarifCont.classList.add('hidden');
        }
        
        // Clear response area
        const responseCont = document.getElementById('chatbot-response');
        if (responseCont) {
            responseCont.classList.add('hidden');
        }
        const contactBtnCont = document.getElementById('contact-button-container');
        if (contactBtnCont) {
            contactBtnCont.classList.add('hidden');
        }
        
        // Show greeting
        const greeting = document.getElementById('chatbot-greeting');
        if (greeting) {
            greeting.classList.remove('hidden');
        }
        
        // Reset clarification flag
        clarificationActive = false;
        
        // Reload greeting categories
        loadGreeting();
    }

    // Send message
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = input.value.trim();
        
        if (!message) return;

        // BUG FIX 2: Reset clarification flag when user sends new query
        clarificationActive = false;

        // BUG FIX 3: Check for greeting first - bypass all other flows
        if (isGreetingQuery(message)) {
            addMessage(message, 'user');
            input.value = '';
            clearInputBtn.classList.add('hidden');
            searchSuggestionsContainer.classList.add('hidden');
            
            showTypingIndicator();
            setTimeout(() => {
                hideTypingIndicator();
                addMessage(getGreetingResponse(), 'bot');
            }, 500);
            return;
        }

        // Add user message
        addMessage(message, 'user');
        input.value = '';
        clearInputBtn.classList.add('hidden');
        searchSuggestionsContainer.classList.add('hidden');

        // Show typing indicator
        showTypingIndicator();

        try {
            // BUG FIX 3: Clear previous results before new query
            clearPreviousResults();
            
            // Hide any stale containers
            hideAllContainers();

            // Step 1: Check for ambiguity first
            const ambiguityResponse = await fetch('{{ route("chatbot.check-ambiguity") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ message })
            });

            const ambiguityData = await window.safeJson(ambiguityResponse) || {};

            // If query is ambiguous, show clarification and stop
            if (ambiguityData.success && ambiguityData.is_ambiguous && ambiguityData.clarification) {
                hideTypingIndicator();
                showClarificationUI(ambiguityData.clarification, message);
                return;
            }

            // Step 2: Not ambiguous, proceed with retrieval
            const response = await fetch('{{ route("chatbot.get-response") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ message })
            });

            const data = await window.safeJson(response) || {};

            // Hide typing indicator
            hideTypingIndicator();

            if (data.success) {
                // Add bot response
                addMessage(data.response, 'bot');

                // Show articles if available
                if (data.articles && data.articles.length > 0) {
                    showArticles(data.articles);
                }

                // Show contact button if suggested
                if (data.show_contact_button) {
                    responseContainer.classList.remove('hidden');
                    contactButtonContainer.classList.remove('hidden');
                    if (data.contact_button_text) {
                        contactStaffBtn.textContent = data.contact_button_text;
                    }
                }
            } else {
                addMessage(data.response, 'bot');
            }
        } catch (error) {
            console.error('Error:', error);
            hideTypingIndicator();
            addMessage('Maaf, terjadi kesalahan. Silakan coba lagi.', 'bot');
        }
    });

    // Handle ticket form submission
    ticketForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(ticketForm);
        const data = Object.fromEntries(formData);

        try {
            const response = await fetch('{{ route("chatbot.create-ticket") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(data)
            });

            const result = await window.safeJson(response) || {};

            if (result.success) {
                addMessage('✅ ' + result.message, 'bot');
                formContainer.classList.add('hidden');
                ticketForm.reset();
                
                setTimeout(() => {
                    widget.classList.remove('show');
                    toggle.classList.remove('hide');
                }, 2000);
            }
        } catch (error) {
            console.error('Error:', error);
            addMessage('Maaf, terjadi kesalahan saat membuat tiket.', 'bot');
        }
    });

    // Handle contact staff button
    contactStaffBtn.addEventListener('click', () => {
        const lastUserMessage = messagesContainer.querySelector('.user-message:last-child .message-bubble');
        const message = lastUserMessage ? lastUserMessage.textContent : '';
        document.getElementById('form-message').value = message;
        showTicketForm(message);
        contactButtonContainer.classList.add('hidden');
    });

    cancelTicketBtn.addEventListener('click', hideTicketForm);

    // Load greeting with categories
    async function loadGreeting() {
        console.log('[CHATBOT] loadGreeting called');
        try {
            const response = await fetch('{{ route("chatbot.greeting") }}');
            const data = await window.safeJson(response) || {};
            console.log('[CHATBOT] Greeting response:', data);

            if (data.success) {
                updateGreeting(data.greeting);
                renderCategories(data.categories);
            }
        } catch (error) {
            console.error('[CHATBOT] Error loading greeting:', error);
        }
    }

    // Update greeting message
    function updateGreeting(greeting) {
        const greetingEl = document.querySelector('[data-chatbot-greeting-message]');
        console.log('[CHATBOT] updateGreeting called, element found:', !!greetingEl);
        if (greetingEl) {
            greetingEl.innerHTML = `<p class="text-sm font-semibold">${greeting.replace(/\n/g, '<br>')}</p>`;
        }
    }

    // Render category chips
    function renderCategories(categories) {
        const container = document.querySelector('[data-chatbot-categories]');
        console.log('[CHATBOT] renderCategories called, container found:', !!container, 'categories:', categories ? categories.length : 0);
        if (!container) {
            console.error('[CHATBOT] Category container not found');
            return;
        }
        container.innerHTML = '';

        categories.forEach(category => {
            const chip = document.createElement('button');
            chip.className = 'category-chip';
            chip.textContent = category.label;
            chip.addEventListener('click', () => handleCategoryClick(category));
            container.appendChild(chip);
        });
    }

    // Handle category click
    async function handleCategoryClick(category) {
        addMessage(category.label, 'user');
        
        // Hide greeting
        document.getElementById('chatbot-greeting').classList.add('hidden');

        showTypingIndicator();

        try {
            const response = await fetch('{{ route("chatbot.category-subtopics") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ category_id: category.id })
            });

            const data = await window.safeJson(response) || {};
            hideTypingIndicator();

            if (data.success) {
                addMessage(data.question, 'bot');
                showSubtopics(data.subtopics);
            }
        } catch (error) {
            console.error('Error:', error);
            hideTypingIndicator();
            addMessage('Maaf, terjadi kesalahan. Silakan coba lagi.', 'bot');
        }
    }

    // Show subtopics
    function showSubtopics(subtopics) {
        subtopicsList.innerHTML = '';
        subtopicQuestion.textContent = 'Pilih yang paling sesuai:';
        
        subtopics.forEach(subtopic => {
            const chip = document.createElement('button');
            chip.className = 'suggestion-chip';
            chip.textContent = subtopic.label;
            chip.addEventListener('click', () => handleSubtopicClick(subtopic));
            subtopicsList.appendChild(chip);
        });

        subtopicsContainer.classList.remove('hidden');
    }

    // Handle subtopic click
    async function handleSubtopicClick(subtopic) {
        addMessage(subtopic.label, 'user');
        subtopicsContainer.classList.add('hidden');

        // BUG FIX 2: Mark that user has responded to clarification
        clarificationActive = false;

        showTypingIndicator();

        try {
            // BUG FIX 3: Clear previous results
            clearPreviousResults();

            // Use the full title for better retrieval
            const response = await fetch('{{ route("chatbot.get-response") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ message: subtopic.full_title })
            });

            const data = await window.safeJson(response) || {};
            hideTypingIndicator();

            if (data.success) {
                addMessage(data.response, 'bot');

                // Show articles if available
                if (data.articles && data.articles.length > 0) {
                    showArticles(data.articles);
                }
            }
        } catch (error) {
            console.error('Error:', error);
            hideTypingIndicator();
            addMessage('Maaf, terjadi kesalahan. Silakan coba lagi.', 'bot');
        }
    }

    // Show clarification UI for ambiguous queries
    function showClarificationUI(clarification, originalMessage) {
        // BUG FIX 2: Prevent recursive clarification
        if (clarificationActive) {
            // Already in clarification mode, skip and go directly to retrieval
            input.value = originalMessage;
            form.dispatchEvent(new Event('submit'));
            return;
        }
        
        clarificationActive = true;
        clarificationQuestion.textContent = clarification.question || 'Bisa lebih spesifik? 😊';
        clarificationSuggestions.innerHTML = '';

        const suggestions = clarification.suggestions || [];
        
        suggestions.forEach(suggestion => {
            const chip = document.createElement('button');
            chip.className = 'suggestion-chip';
            chip.textContent = suggestion.label;
            chip.addEventListener('click', () => {
                addMessage(suggestion.label, 'user');
                clarificationContainer.classList.add('hidden');
                
                // BUG FIX 2: Reset clarification flag
                clarificationActive = false;
                
                // BUG FIX 3: Clear previous results
                clearPreviousResults();
                
                // Build a more specific message by combining context with original query
                const specificMessage = `${suggestion.label} ${originalMessage}`;
                input.value = specificMessage;
                form.dispatchEvent(new Event('submit'));
            });
            clarificationSuggestions.appendChild(chip);
        });

        clarificationContainer.classList.remove('hidden');
    }

    // Show clarification (legacy - for backward compatibility)
    function showClarification(suggestions) {
        clarificationQuestion.textContent = 'Bisa lebih spesifik? 😊';
        clarificationSuggestions.innerHTML = '';

        suggestions.forEach(suggestion => {
            const chip = document.createElement('button');
            chip.className = 'suggestion-chip';
            chip.textContent = suggestion.label;
            chip.addEventListener('click', () => {
                addMessage(suggestion.label, 'user');
                clarificationContainer.classList.add('hidden');
                
                // Trigger search with this suggestion
                input.value = suggestion.label;
                form.dispatchEvent(new Event('submit'));
            });
            clarificationSuggestions.appendChild(chip);
        });

        clarificationContainer.classList.remove('hidden');
    }

    // Load search suggestions
    async function loadSearchSuggestions(query) {
        try {
            const response = await fetch('{{ route("chatbot.search-suggestions") }}', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            const data = await window.safeJson(response) || {};

            if (data.success && data.suggestions.length > 0) {
                renderSearchSuggestions(data.suggestions);
            } else {
                searchSuggestionsContainer.classList.add('hidden');
            }
        } catch (error) {
            console.error('Error loading search suggestions:', error);
        }
    }

    // Render search suggestions
    function renderSearchSuggestions(suggestions) {
        searchSuggestionsList.innerHTML = '';

        suggestions.forEach(suggestion => {
            const item = document.createElement('button');
            item.className = 'w-full text-left px-4 py-2 hover:bg-gray-50 transition-colors';
            item.innerHTML = `
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <span class="text-sm text-gray-700">${suggestion.label}</span>
                </div>
            `;
            item.addEventListener('click', () => {
                input.value = suggestion.label;
                searchSuggestionsContainer.classList.add('hidden');
                clearInputBtn.classList.remove('hidden');
                input.focus();
            });
            searchSuggestionsList.appendChild(item);
        });

        searchSuggestionsContainer.classList.remove('hidden');
    }

    // Show article cards
    function showArticles(articles) {
        articlesContainer.classList.remove('hidden');
        const articlesList = document.getElementById('articles-list');
        articlesList.innerHTML = '';

        articles.forEach((article, index) => {
            const card = document.createElement('div');
            card.className = 'group bg-white border border-gray-200 rounded-xl p-3 hover:border-red-400 hover:shadow-md hover:shadow-red-100 transition-all duration-200 cursor-pointer';
            
            // Determine confidence badge styling
            let confidenceBadge = '';
            if (article.confidence === 'high') {
                confidenceBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Sangat Relevan</span>';
            } else if (article.confidence === 'medium') {
                confidenceBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Relevan</span>';
            } else {
                confidenceBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Mungkin Relevan</span>';
            }
            
            // Build card content
            let cardContent = `<div class="flex items-start gap-3">`;
            
            // Icon with background
            cardContent += `<div class="flex-shrink-0 w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center group-hover:bg-red-100 transition-colors">`;
            cardContent += `<svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">`;
            cardContent += `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>`;
            cardContent += `</svg></div>`;
            
            cardContent += `<div class="flex-1 min-w-0">`;
            
            // Title with link
            cardContent += `<a href="{{ url('/articles') }}/${article.slug}" target="_blank" class="text-sm font-semibold text-gray-900 hover:text-red-600 transition-colors line-clamp-2">`;
            cardContent += article.title;
            cardContent += `</a>`;
            
            // Excerpt
            if (article.excerpt) {
                cardContent += `<p class="text-xs text-gray-500 mt-1.5 line-clamp-2">${article.excerpt}</p>`;
            }
            
            // Meta row
            cardContent += `<div class="flex items-center gap-2 mt-2 flex-wrap">`;
            cardContent += confidenceBadge;
            
            if (article.similarity) {
                cardContent += `<span class="text-xs text-gray-400">•</span>`;
                cardContent += `<span class="text-xs text-gray-500">${(article.similarity * 100).toFixed(0)}% kecocokan</span>`;
            }
            
            if (article.category_name) {
                cardContent += `<span class="text-xs text-gray-400">•</span>`;
                cardContent += `<span class="text-xs text-gray-500">${article.category_name}</span>`;
            }
            
            cardContent += `</div></div>`;
            
            // External link arrow
            cardContent += `<div class="flex-shrink-0 self-center">`;
            cardContent += `<svg class="w-4 h-4 text-gray-300 group-hover:text-red-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">`;
            cardContent += `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>`;
            cardContent += `</svg></div>`;
            
            cardContent += `</div>`;
            card.innerHTML = cardContent;
            
            articlesList.appendChild(card);
        });
        
        // Scroll to bottom
        articlesContainer.scrollTop = articlesContainer.scrollHeight;
    }

    // Helper functions
    function addMessage(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `flex ${sender === 'user' ? 'justify-end' : 'justify-start'} mb-3`;
        
        const bubble = document.createElement('div');
        bubble.className = `message-bubble ${sender === 'bot' ? 'bot-bubble' : 'user-bubble'}`;
        bubble.innerHTML = text;
        
        messageDiv.appendChild(bubble);
        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function showTypingIndicator() {
        const indicatorDiv = document.createElement('div');
        indicatorDiv.id = 'typing-indicator-message';
        indicatorDiv.className = 'flex justify-start mb-3';
        indicatorDiv.innerHTML = '<div class="bot-bubble message-bubble flex items-center gap-1"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></div>';
        messagesContainer.appendChild(indicatorDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function hideTypingIndicator() {
        const indicator = document.getElementById('typing-indicator-message');
        if (indicator) indicator.remove();
    }

    function hideAllContainers() {
        clarificationContainer.classList.add('hidden');
        subtopicsContainer.classList.add('hidden');
        responseContainer.classList.add('hidden');
        formContainer.classList.add('hidden');
        contactButtonContainer.classList.add('hidden');
    }

    function showTicketForm(message) {
        hideAllContainers();
        formContainer.classList.remove('hidden');
        document.getElementById('form-message').value = message;
    }

    function hideTicketForm() {
        formContainer.classList.add('hidden');
        ticketForm.reset();
    }

    // BUG FIX 3: Clear previous suggestion/result containers
    function clearPreviousResults() {
        // Clear articles container
        const articlesList = document.getElementById('articles-list');
        if (articlesList) {
            articlesList.innerHTML = '';
        }
        const articlesContainer = document.getElementById('chatbot-articles');
        if (articlesContainer) {
            articlesContainer.classList.add('hidden');
        }
        
        // Clear subtopics
        const subtopicsList = document.getElementById('subtopics-list');
        if (subtopicsList) {
            subtopicsList.innerHTML = '';
        }
        const subtopicsCont = document.getElementById('chatbot-subtopics');
        if (subtopicsCont) {
            subtopicsCont.classList.add('hidden');
        }
        
        // Clear clarification
        const clarifSuggestions = document.getElementById('clarification-suggestions');
        if (clarifSuggestions) {
            clarifSuggestions.innerHTML = '';
        }
        const clarifCont = document.getElementById('chatbot-clarification');
        if (clarifCont) {
            clarifCont.classList.add('hidden');
        }
        
        // Clear response area
        const responseCont = document.getElementById('chatbot-response');
        if (responseCont) {
            responseCont.classList.add('hidden');
        }
        const contactBtnCont = document.getElementById('contact-button-container');
        if (contactBtnCont) {
            contactBtnCont.classList.add('hidden');
        }
    }

    // Close search suggestions when clicking outside
    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !searchSuggestionsContainer.contains(e.target)) {
            searchSuggestionsContainer.classList.add('hidden');
        }
    });
});
</script>